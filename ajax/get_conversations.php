<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$conversations = [];

// This complex query finds all conversations for the current user,
// and for each conversation, it gets the OTHER user's details (name, avatar).
$stmt = $conn->prepare("
    SELECT 
        c.id as conversation_id,
        u.id as other_user_id,
        u.fname,
        u.lname,
        u.avatar_url,
        (SELECT message_text FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
    FROM conversations c
    JOIN users u ON u.id = IF(c.user_one_id = ?, c.user_two_id, c.user_one_id)
    WHERE c.user_one_id = ? OR c.user_two_id = ?
    ORDER BY c.last_updated DESC
");
$stmt->bind_param("iii", $current_user_id, $current_user_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $conversations[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($conversations);
