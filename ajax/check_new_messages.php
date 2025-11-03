<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unread_count' => 0]);
    exit;
}
$user_id = $_SESSION['user_id'];

// --- THIS IS THE CORRECTED QUERY ---
// It joins 'messages' with 'conversations'
// It checks if you are user_one OR user_two
$stmt = $conn->prepare(
    "SELECT COUNT(m.id) as unread_count 
     FROM messages m
     JOIN conversations c ON m.conversation_id = c.id
     WHERE m.is_read = 0 
       AND m.sender_id != ? 
       AND (c.user_one_id = ? OR c.user_two_id = ?)"
);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode($result);
