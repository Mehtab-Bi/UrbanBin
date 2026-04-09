<?php
session_start();
require_once "log_helper.php"; // ✅ MUST BE HERE

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_hygiene_db";

$REPORT_REWARD_POINTS = 10;

// Check login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Login required.";
    $_SESSION['message_type'] = 'error';
    header("Location: citizen_portal.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $_SESSION['message'] = "Database error.";
    $_SESSION['message_type'] = 'error';
    header("Location: citizen_dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $bin_id = $_POST['bin_id'];
    $report_type = $_POST['report_type'];
    $details = $_POST['description'];

    // Insert report
    $stmt = $conn->prepare("INSERT INTO reports (user_id, bin_id, issue_type, details, points_awarded)
                            VALUES (?, ?, ?, ?, ?)");
    $points = $REPORT_REWARD_POINTS;
    $stmt->bind_param("isssi", $user_id, $bin_id, $report_type, $details, $points);
    $stmt->execute();
    $stmt->close();

    // Update points
    $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $stmt->bind_param("ii", $points, $user_id);
    $stmt->execute();
    $stmt->close();

    // ✅ ADD CITIZEN LOG (THIS IS THE KEY)
    addLog(
        "Citizen",
        "Citizen reported issue: $report_type on bin $bin_id",
        "REPORT",
        "CITIZEN",
        $bin_id
    );

    $_SESSION['message'] = "Report submitted successfully!";
    $_SESSION['message_type'] = 'success';

    header("Location: citizen_dashboard.php");
    exit;
}
?>
