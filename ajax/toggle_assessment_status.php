<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if ($_SESSION['role'] !== 'teacher') { /* ... unauthorized error ... */
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$status = $data['status'] ?? null;

if (!$id || !in_array($status, ['open', 'closed'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit;
}

$stmt = $conn->prepare("UPDATE assessments SET status = ? WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("sii", $status, $id, $_SESSION['user_id']);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
