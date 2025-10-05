<?php
// FILE: ajax/get_or_create_conversation.php
session_start();
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['other_user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

$user1 = $_SESSION['user_id'];
$user2 = (int)$_POST['other_user_id'];
$conversation_id = null;

// Check if a conversation already exists
$stmt = $conn->prepare("SELECT id FROM conversations WHERE (user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?)");
$stmt->bind_param("iiii", $user1, $user2, $user2, $user1);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $conversation_id = $row['id'];
} else {
    // If not, create a new conversation
    $stmt = $conn->prepare("INSERT INTO conversations (user_one_id, user_two_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user1, $user2);
    $stmt->execute();
    $conversation_id = $conn->insert_id;
}
$stmt->close();
$conn->close();

echo json_encode(['conversation_id' => $conversation_id]);
