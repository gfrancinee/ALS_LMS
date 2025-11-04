<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Send a 'Forbidden' status, which is more correct
    echo json_encode(['unread_count' => 0, 'error' => 'Unauthorized']);
    exit;
}
$user_id = $_SESSION['user_id'];

$unread_count = 0; // Default value in case anything fails

$stmt = $conn->prepare("SELECT COUNT(id) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        // Check if we actually got a row back
        if ($result_row = $result->fetch_assoc()) {
            // Ensure the count is an integer
            $unread_count = (int)$result_row['unread_count'];
        }
    }
    // else: statement execute failed, count remains 0
    $stmt->close();
}
// else: statement prepare failed, count remains 0

$conn->close();

// Always return a valid JSON object like {"unread_count": 0}
echo json_encode(['unread_count' => $unread_count]);
