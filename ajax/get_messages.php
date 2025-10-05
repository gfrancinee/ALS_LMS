<?php
// FILE: ajax/get_messages.php
session_start();
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['conversation_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

$conversation_id = (int)$_GET['conversation_id'];
$messages = [];

$stmt = $conn->prepare("SELECT m.sender_id, m.message_text, m.created_at, u.fname, u.avatar_url FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.conversation_id = ? ORDER BY m.created_at ASC");
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($messages);
