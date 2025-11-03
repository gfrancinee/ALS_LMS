<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$user_id = $_SESSION['user_id'];
$conversation_id = $_POST['conversation_id'] ?? null;

if ($conversation_id) {
    // --- SCENARIO A: Student/Teacher clicked a specific chat ---
    // (This logic was already correct)
    $stmt = $conn->prepare(
        "UPDATE messages 
         SET is_read = 1 
         WHERE conversation_id = ? AND sender_id != ?"
    );
    $stmt->bind_param("ii", $conversation_id, $user_id);
    $stmt->execute();
    $stmt->close();
} else {
    // --- SCENARIO B: Admin clicked the floating icon (mark all) ---
    // --- THIS IS THE CORRECTED QUERY ---
    $stmt = $conn->prepare(
        "UPDATE messages m
         JOIN conversations c ON m.conversation_id = c.id
         SET m.is_read = 1
         WHERE m.is_read = 0
           AND m.sender_id != ?
           AND (c.user_one_id = ? OR c.user_two_id = ?)"
    );
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
echo json_encode(['success' => true]);
