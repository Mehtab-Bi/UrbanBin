<?php
session_start();

// Operator or Admin allowed
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['operator','admin'])) {
    header("Location: login_selection.php");
    exit;
}

if (!isset($_POST['bin_id'])) {
    header("Location: operator_bins_status.php");
    exit;
}

$bin_id = $_POST['bin_id'];

// DB
$conn = new mysqli("localhost", "root", "", "smart_hygiene_db");

if ($conn->connect_error) {
    die("Database error: " . $conn->connect_error);
}

$sql = "
UPDATE bins SET
    capacity_percent = 0,
    last_capacity_percent = 0,
    co2 = 450,
    ammonia = 0.05,
    hygiene_status = 'Normal',
    status = 'Normal',
    time_to_fullness_hrs = 999,
    last_updated = NOW()
WHERE bin_id = '$bin_id'
";

$conn->query($sql);

// Optional logging
file_put_contents("operator_log.txt", 
    date("Y-m-d H:i:s") . " - Operator {$_SESSION['username']} serviced $bin_id\n",
    FILE_APPEND
);

header("Location: operator_bins_status.php?serviced=$bin_id");
exit;
?>
