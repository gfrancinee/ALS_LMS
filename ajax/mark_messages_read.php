<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['conversation_id'])) {
    exit;
}
$user_id = $_SESSION['user_id'];
$conversation_id = $_POST['conversation_id'];

// Mark all messages in this conversation that were sent by OTHERS as read
$stmt = $conn->prepare(
    "UPDATE messages 
     SET is_read = 1 
     WHERE conversation_id = ? AND sender_id != ?"
);
$stmt->bind_param("ii", $conversation_id, $user_id);
$stmt->execute();
