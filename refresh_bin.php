<?php
header("Content-Type: application/json");

// SECURITY OPTIONAL (enable if needed)
// session_start();
// if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
//     echo json_encode(["error" => "Unauthorized"]);
//     exit;
// }

if (!isset($_GET['bin_id'])) {
    echo json_encode(["error" => "Missing bin_id"]);
    exit;
}

$bin_id = $_GET['bin_id'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_hygiene_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

$sql = "SELECT bin_id, capacity_percent, co2, ammonia, hygiene_status, last_updated 
        FROM bins WHERE bin_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $bin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["error" => "Bin not found"]);
    exit;
}

$row = $result->fetch_assoc();

// Format output cleanly
echo json_encode([
    "bin_id"          => $row["bin_id"],
    "capacity_percent" => (int)$row["capacity_percent"],
    "co2"             => (float)$row["co2"],
    "ammonia"         => (float)$row["ammonia"],
    "hygiene_status"  => $row["hygiene_status"],
    "last_updated"    => $row["last_updated"]
]);

$stmt->close();
$conn->close();
?>
