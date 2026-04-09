<?php
session_start();
include "log_helper.php";

// Ensure admin is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_selection.php");
    exit;
}

// Check ID
if (!isset($_POST['id'])) {
    header("Location: admin_users.php?error=invalid_id");
    exit;
}

$id = intval($_POST['id']);
$username = trim($_POST['username'] ?? "");
$role     = trim($_POST['role'] ?? "");
$password = trim($_POST['password'] ?? "");

// Validate main fields
if ($username === "" || $role === "") {
    header("Location: admin_users.php?error=empty_fields");
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "smart_hygiene_db");

// Fetch old username (for logs)
$res = mysqli_query($conn, "SELECT username FROM users WHERE id=$id");
$row = mysqli_fetch_assoc($res);
$oldUsername = $row['username'] ?? "Unknown";

// Build update query based on password update
if ($password !== "") {
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $sql = "UPDATE users 
            SET username='$username', role='$role', password_hash='$hashed' 
            WHERE id=$id";

    $logMessage = "Updated user: $oldUsername → $username (Role: $role, Password changed)";
} else {
    $sql = "UPDATE users 
            SET username='$username', role='$role' 
            WHERE id=$id";

    $logMessage = "Updated user: $oldUsername → $username (Role: $role)";
}

mysqli_query($conn, $sql);

// Log action
addLog("Admin", $logMessage);

header("Location: admin_users.php?updated=1");
exit;
?>
