<?php
// This script is built to handle AJAX requests (like from a modal)
session_start();
require_once '../includes/db.php';
// require_once '../includes/auth.php'; // Make sure this file doesn't `echo` anything
header('Content-Type: application/json');

// Security Check: Ensure an admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// 1. Read the JSON data sent from the JavaScript
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? 0;

if ($id > 0) {
    // 2. Prepare and execute delete query
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // 3. Send a JSON success message
        echo json_encode(['success' => true]);
    } else {
        // 4. Send a JSON error message
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    }
    $stmt->close();
} else {
    // 5. Send a JSON error message for invalid ID
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid user ID.']);
}

$conn->close();
