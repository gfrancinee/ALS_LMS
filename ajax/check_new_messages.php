<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unread_count' => 0]);
    exit;
}

$user_id = $_SESSION['user_id'];

// This is a more reliable query to count unread messages.
// It counts messages that are unread, not sent by you,
// and are in conversations that you are a part of.
$stmt = $conn->prepare(
    "SELECT COUNT(id) as unread_count 
     FROM messages 
     WHERE is_read = 0 
       AND sender_id != ? 
       AND conversation_id IN (SELECT conversation_id FROM participants WHERE user_id = ?)"
);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode($result);
