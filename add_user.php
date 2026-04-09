<?php
session_start();
include "log_helper.php";

// Admin check
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_selection.php");
    exit;
}

// Check required fields
if (!isset($_POST['username']) || !isset($_POST['password']) || !isset($_POST['role'])) {
    header("Location: admin_users.php?error=invalid");
    exit;
}

$username = trim($_POST['username']);
$password = trim($_POST['password']);
$role     = trim($_POST['role']);

if ($username === "" || $password === "" || $role === "") {
    header("Location: admin_users.php?error=empty_fields");
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "smart_hygiene_db");

// Hash password correctly
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Insert user into correct columns
$sql = "INSERT INTO users (username, password_hash, role) 
        VALUES ('$username', '$hashed', '$role')";

mysqli_query($conn, $sql);

// Log the action
addLog("Admin", "Added new user: $username (Role: $role)");

header("Location: admin_users.php?added=1");
exit;
?>
