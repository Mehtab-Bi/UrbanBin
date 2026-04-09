<?php
// Configuration (must match your setup)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_hygiene_db";

// 1. Generate a brand new, clean hash for the password "password123"
$new_hash = password_hash("password123", PASSWORD_DEFAULT);

// 2. Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("<div style='background-color:#fee2e2; padding:15px; border:1px solid #f87171; color:#b91c1c; font-weight:bold;'>CONNECTION ERROR: " . $conn->connect_error . "</div>");
}

// 3. Update the admin user with the new, guaranteed-valid hash
// We use a prepared statement for security, though this is a one-time script.
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
$stmt->bind_param("s", $new_hash);

if ($stmt->execute()) {
    echo "<div style='background-color:#d1fae5; padding:20px; border:2px solid #34d399; color:#065f46; font-weight:bold; font-size:1.2rem; border-radius:8px;'>";
    echo "SUCCESS: Admin password hash has been reset and fixed.<br>";
    echo "New Hash: <code style='color: #4f46e5;'>" . htmlspecialchars($new_hash) . "</code><br>";
    echo "Please <a href='login.php' style='color:#1e40af; text-decoration:underline;'>return to the login page</a> and try again.";
    echo "</div>";
} else {
    echo "<div style='background-color:#fee2e2; padding:15px; border:1px solid #f87171; color:#b91c1c; font-weight:bold;'>ERROR UPDATING HASH: " . $conn->error . "</div>";
}

$stmt->close();
$conn->close();

?>
