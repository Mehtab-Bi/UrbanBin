<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function addLog($role, $activity, $action = "GENERAL", $module = "SYSTEM", $bin_id = null) {

    $role = ucfirst(strtolower($role)); // normalize role

    $conn = new mysqli("localhost", "root", "", "smart_hygiene_db");
    if ($conn->connect_error) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? "UNKNOWN";

    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_role, bin_id, activity, action, module, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("ssssss", $role, $bin_id, $activity, $action, $module, $ip);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
?>
