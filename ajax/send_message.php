<?php
// FILE: ajax/send_message.php
session_start();
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['conversation_id']) || !isset($_POST['message_text'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters.']);
    exit();
}

$conversation_id = (int)$_POST['conversation_id'];
$sender_id = $_SESSION['user_id'];
$message_text = trim($_POST['message_text']);

if (empty($message_text)) {
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty.']);
    exit();
}

// Start a transaction
$conn->begin_transaction();

try {
    // Insert the new message
    $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, message_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $conversation_id, $sender_id, $message_text);
    $stmt->execute();

    // Update the last_updated timestamp in the conversations table
    $stmt = $conn->prepare("UPDATE conversations SET last_updated = NOW() WHERE id = ?");
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();

    // Commit the transaction
    $conn->commit();

    echo json_encode(['status' => 'success']);
} catch (mysqli_sql_exception $exception) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}

$stmt->close();
$conn->close();
