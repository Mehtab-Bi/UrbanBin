<?php
session_start();

// ----------------------------------------------
// HEADERS
// ----------------------------------------------
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// ----------------------------------------------
// HELPER RESPONSE (used only when not redirecting)
// ----------------------------------------------
function sendResponse($status, $message, $extra = []) {
    http_response_code($status);
    echo json_encode(array_merge(["message" => $message], $extra));
    exit;
}

// ----------------------------------------------
// CORS PREFLIGHT
// ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendResponse(200, "OK");
}

// ----------------------------------------------
// METHOD CHECK
// ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, "Method Not Allowed. Use POST.");
}

// ----------------------------------------------
// AUTH CHECK (CITIZEN ONLY)
// ----------------------------------------------
if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true ||
    $_SESSION['role'] !== 'citizen'
) {
    sendResponse(403, "Forbidden. Login required.");
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    sendResponse(401, "Unauthorized: No user ID in session.");
}

// ----------------------------------------------
// READ JSON OR FORM POST
// ----------------------------------------------
$raw = file_get_contents("php://input");
$json = json_decode($raw, true);

if (json_last_error() === JSON_ERROR_NONE && !empty($json)) {
    $data = $json;
} elseif (!empty($_POST)) {
    $data = $_POST;
} else {
    sendResponse(400, "No valid data received.");
}

// ----------------------------------------------
// ACCEPT BOTH NEW + OLD FIELD NAMES
// ----------------------------------------------
$bin_id = $data['bin_id'] ?? null;

$issue_type = $data['issue_type']
           ?? $data['report_type']
           ?? null;

$details = $data['description']
        ?? $data['details']
        ?? "";

// ----------------------------------------------
// VALIDATION
// ----------------------------------------------
if (empty($bin_id) || empty($issue_type)) {
    sendResponse(400, "Missing required fields: bin_id, issue_type");
}

// ----------------------------------------------
// DATABASE CONNECTION
// ----------------------------------------------
$conn = new mysqli("localhost", "root", "", "smart_hygiene_db");

if ($conn->connect_error) {
    sendResponse(500, "DB Connection Failed: " . $conn->connect_error);
}

// ----------------------------------------------
// TRANSACTION START
// ----------------------------------------------
$conn->autocommit(false);
$success = true;
$points = 10;

// ----------------------------------------------
// INSERT REPORT
// ----------------------------------------------
$stmt = $conn->prepare("
    INSERT INTO reports (user_id, bin_id, issue_type, details, points_awarded)
    VALUES (?, ?, ?, ?, ?)
");

if ($stmt) {
    $stmt->bind_param("isssi", $user_id, $bin_id, $issue_type, $details, $points);
    if (!$stmt->execute()) $success = false;
    $stmt->close();
} else {
    $success = false;
}

// ----------------------------------------------
// UPDATE USER POINTS
// ----------------------------------------------
if ($success) {
    $stmt2 = $conn->prepare("
        UPDATE users SET points = points + ?
        WHERE id = ?
    ");
    if ($stmt2) {
        $stmt2->bind_param("ii", $points, $user_id);
        if (!$stmt2->execute()) $success = false;
        $stmt2->close();
    } else {
        $success = false;
    }
}

// ----------------------------------------------
// LOG TRANSACTION
// ----------------------------------------------
if ($success) {
    $stmt3 = $conn->prepare("
        INSERT INTO point_transactions (user_id, points_change, reason, transaction_date)
        VALUES (?, ?, ?, NOW())
    ");
    if ($stmt3) {
        $reason = "Citizen Report Submission Reward ($issue_type)";
        $stmt3->bind_param("iis", $user_id, $points, $reason);
        if (!$stmt3->execute()) $success = false;
        $stmt3->close();
    } else {
        $success = false;
    }
}

// ----------------------------------------------
// COMMIT / ROLLBACK
// ----------------------------------------------
if ($success) {
    $conn->commit();

    // Mark success for thank-you page
    $_SESSION['last_report_success'] = true;

    // Redirect to beautiful success page
    header("Location: report_success.php");
    exit;

} else {
    $conn->rollback();
    sendResponse(500, "Report submission failed. Please try again.");
}

$conn->close();
?>
