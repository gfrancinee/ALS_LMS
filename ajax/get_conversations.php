<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// --- THIS IS THE FIX ---
// Added a period '.' after $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$conversations = [];

// This query works for Admins, Teachers, and Students.
$stmt = $conn->prepare("
    SELECT 
        c.id as conversation_id,
        c.last_updated,
        u.id as other_user_id,
        u.fname,
        u.lname,
        u.avatar_url,
        
        (SELECT message_text FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
        
        (SELECT COUNT(m_unread.id) FROM messages m_unread 
         WHERE m_unread.conversation_id = c.id 
         AND m_unread.is_read = 0 
         AND m_unread.sender_id != ?) as unread_count
         
    FROM conversations c
    JOIN users u ON u.id = IF(c.user_one_id = ?, c.user_two_id, c.user_one_id)
    WHERE c.user_one_id = ? OR c.user_two_id = ?
    ORDER BY c.last_updated DESC
");
$stmt->bind_param("iiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    if ($row['last_updated']) {
        $msg_time = strtotime($row['last_updated']);
        if (date('Ymd') == date('Ymd', $msg_time)) {
            $row['last_message_time'] = date('g:i A', $msg_time); // e.g., 5:30 PM
        } else {
            $row['last_message_time'] = date('M j', $msg_time); // e.g., Oct 31
        }
    } else {
        $row['last_message_time'] = '';
    }

    $row['other_user_name'] = $row['fname'] . ' ' . $row['lname'];

    $conversations[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($conversations);
