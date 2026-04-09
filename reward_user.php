<?php
// Set headers for JSON response and handle CORS
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_hygiene_db";

/**
 * Sends a structured JSON response and exits the script.
 * @param int $status HTTP status code.
 * @param string $message Response message.
 * @param array $data Additional data to include in the response.
 */
function sendResponse($status, $message, $data = []) {
    http_response_code($status);
    echo json_encode(array_merge(['message' => $message], $data));
    exit;
}

// 1. Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, "Method Not Allowed. Only POST requests are accepted.");
}

$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
    sendResponse(400, "Bad Request: Invalid JSON data received.");
}

// 2. Validate essential fields
$user_id = $data['user_id'] ?? null;
$action_name = $data['action_name'] ?? null;
$notes = $data['notes'] ?? null; // Optional note for the log

if (!is_numeric($user_id) || empty($action_name)) {
    sendResponse(400, "Missing or invalid parameters: user_id (int) and action_name (string) are required.");
}

// 3. Database Connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    sendResponse(500, "Database connection failed: " . $conn->connect_error);
}

$conn->begin_transaction(); // Start transaction for atomic updates

try {
    // A. Fetch reward details (points value and ID)
    $stmt = $conn->prepare("SELECT reward_id, points_value FROM rewards WHERE action_name = ?");
    $stmt->bind_param("s", $action_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $conn->rollback();
        sendResponse(404, "Reward action '{$action_name}' not found in the rewards table.", ['user_id' => $user_id]);
    }

    $reward = $result->fetch_assoc();
    $reward_id = $reward['reward_id'];
    $points_earned = $reward['points_value'];
    $stmt->close();

    // B. Log the reward transaction
    $stmt = $conn->prepare("INSERT INTO user_rewards_log (user_id, reward_id, points_earned, notes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $user_id, $reward_id, $points_earned, $notes);
    if (!$stmt->execute()) {
        throw new Exception("Error logging reward: " . $stmt->error);
    }
    $stmt->close();

    // C. Update or Insert the user's total points in user_points

    // Check if user exists in user_points
    $stmt = $conn->prepare("SELECT user_id FROM user_points WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        // User exists, UPDATE total_points
        $sql = "UPDATE user_points SET total_points = total_points + ?, last_action_at = NOW() WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $points_earned, $user_id);
    } else {
        // User is new, INSERT new row
        $sql = "INSERT INTO user_points (user_id, total_points, last_action_at) VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $points_earned);
    }

    if (!$stmt->execute()) {
        throw new Exception("Error updating user points: " . $stmt->error);
    }
    $stmt->close();

    $conn->commit(); // All successful, commit transaction

    sendResponse(200, "Reward granted successfully.", [
        'user_id' => $user_id,
        'action' => $action_name,
        'points_earned' => $points_earned,
        'log_id' => $conn->insert_id // This might be the log_id or a general insert_id
    ]);

} catch (Exception $e) {
    $conn->rollback(); // Something failed, rollback changes
    error_log("Reward Error: " . $e->getMessage());
    sendResponse(500, "An error occurred during reward processing: " . $e->getMessage());
}

$conn->close();
?>
