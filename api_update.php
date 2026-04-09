<?php
// Set headers for JSON response and handle CORS
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// DB connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_hygiene_db";

// Response function
function sendResponse($status, $message, $data = []) {
    http_response_code($status);
    echo json_encode([
        'message' => $message,
        'hygiene_status' => $data['hygiene_status'] ?? null
    ]);
    exit;
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, "Method Not Allowed. Only POST allowed.");
}

$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
    sendResponse(400, "Invalid JSON received.");
}

// Extract & validate fields
$bin_id = $data['bin_id'] ?? null;
$capacity_percent = $data['capacity_percent'] ?? null;
$co2 = $data['co2'] ?? null;
$ammonia = $data['ammonia'] ?? null;

if (empty($bin_id) || !is_numeric($capacity_percent) || !is_numeric($co2) || !is_numeric($ammonia)) {
    sendResponse(400, "Missing or invalid fields (bin_id, capacity_percent, co2, ammonia).");
}

$capacity_percent = (int)$capacity_percent;
$co2 = (float)$co2;
$ammonia = (float)$ammonia;

// -------------------------------
// HYGIENE STATUS CALCULATION
// -------------------------------
$hygiene_status = 'Normal';

if ($ammonia > 3.0 || $co2 > 2500) {
    $hygiene_status = 'Immediate Hygiene Alert';
} elseif ($ammonia > 1.5 || $co2 > 1500) {
    $hygiene_status = 'Hygiene Service Recommended';
} elseif ($co2 > 800) {
    $hygiene_status = 'Ventilation Suggested';
}

// Fill-Status (BUT only if hygiene is normal, keep priority)
$fill_status = '';
if ($capacity_percent >= 95) {
    $fill_status = 'Fullness Alert';
} elseif ($capacity_percent >= 75) {
    $fill_status = 'Service Soon';
}

if ($hygiene_status !== 'Normal') {
    $final_status = $hygiene_status;
} elseif ($fill_status !== '') {
    $final_status = $fill_status;
} else {
    $final_status = 'Normal';
}

// -----------------------------------
// DB CONNECT
// -----------------------------------
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    sendResponse(500, "Database connection failed: " . $conn->connect_error);
}

// --------------------------------------------
// STEP 1: Get previous capacity & timestamp
// --------------------------------------------
$query_prev = "SELECT capacity_percent, last_updated FROM bins WHERE bin_id = ?";
$stmt_prev = $conn->prepare($query_prev);
$stmt_prev->bind_param("s", $bin_id);
$stmt_prev->execute();
$res_prev = $stmt_prev->get_result();

if ($res_prev->num_rows === 0) {
    sendResponse(404, "Bin not found in database.");
}

$prev = $res_prev->fetch_assoc();
$prev_capacity = $prev['capacity_percent'];
$prev_time = $prev['last_updated'];

// ------------------------------------------------------
// STEP 2: UPDATE bins table including last_* fields
// ------------------------------------------------------
$sql_update = "
    UPDATE bins SET 
        last_capacity_percent = ?,
        last_updated_time = ?,
        capacity_percent = ?,
        co2 = ?,
        ammonia = ?,
        hygiene_status = ?,
        last_updated = NOW()
    WHERE bin_id = ?
";

$stmt = $conn->prepare($sql_update);
$stmt->bind_param(
    "ssisiss",
    $prev_capacity,
    $prev_time,
    $capacity_percent,
    $co2,
    $ammonia,
    $final_status,
    $bin_id
);

if ($stmt->execute()) {
    sendResponse(200, "Bin updated successfully.", [
        "hygiene_status" => $final_status
    ]);
}

sendResponse(500, "Database update failed.");
?>
