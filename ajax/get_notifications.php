<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode([]); // Return an empty array on failure
    exit;
}
$user_id = $_SESSION['user_id'];

// --- FIX ---
// 1. Sort by created_at DESC, because your JavaScript displays 'created_at'.
// 2. This query is simple and returns only the notifications.
$stmt = $conn->prepare(
    "SELECT * FROM notifications 
     WHERE user_id = ? 
     ORDER BY created_at DESC 
     LIMIT 20"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// 3. Send the raw array, which is what your JavaScript expects.
echo json_encode($notifications);
