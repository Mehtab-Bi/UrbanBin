<?php
session_start();
include "log_helper.php";

// Check if admin is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_selection.php");
    exit;
}

// Validate user ID
if (!isset($_GET['id'])) {
    header("Location: admin_users.php");
    exit;
}

$id = intval($_GET['id']);

// Do NOT allow deletion of main admin ID 1
if ($id == 1) {
    header("Location: admin_users.php?error=cannot_delete_admin");
    exit;
}

// DB Connection
$conn = mysqli_connect("localhost", "root", "", "smart_hygiene_db");

// Fetch username before deleting
$res = mysqli_query($conn, "SELECT username FROM users WHERE id=$id");
$row = mysqli_fetch_assoc($res);
$username = $row['username'] ?? "Unknown";

// Delete user
mysqli_query($conn, "DELETE FROM users WHERE id=$id");

// Log this action
addLog("Admin", "Deleted user: $username");

// Redirect back with success
header("Location: admin_users.php?deleted=1");
exit;
?>
