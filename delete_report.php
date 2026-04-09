<?php
session_start();

// Only admin can delete reports
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_selection.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: admin_reports.php?error=no_id");
    exit;
}

$report_id = intval($_GET['id']);

$conn = mysqli_connect("localhost", "root", "", "smart_hygiene_db");

if (!$conn) {
    die("DB Error: " . mysqli_connect_error());
}

// --- Optional: reverse points awarded to citizen for this report ---
$find = mysqli_query($conn, "SELECT user_id, points_awarded FROM reports WHERE id=$report_id");
if ($find && mysqli_num_rows($find) > 0) {
    $row = mysqli_fetch_assoc($find);
    $uid = $row['user_id'];
    $pts = intval($row['points_awarded']);

    mysqli_query($conn, "UPDATE users SET points = GREATEST(points - $pts, 0) WHERE id=$uid");
}

// Delete report
mysqli_query($conn, "DELETE FROM reports WHERE id=$report_id");

// Log the action
include "log_helper.php";
addLog("Admin", "Deleted report ID: $report_id");

header("Location: admin_reports.php?deleted=1");
exit;
?>
