<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id'] ?? null;
$title = trim($data['title'] ?? '');
$description = trim($data['description'] ?? '');
$duration = (int)($data['duration'] ?? 60);
$attempts = (int)($data['attempts'] ?? 1);
$teacher_id = $_SESSION['user_id'];

if (empty($id) || empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Assessment ID and title are required.']);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE assessments SET title = ?, description = ?, duration_minutes = ?, max_attempts = ? WHERE id = ? AND teacher_id = ?"
);
$stmt->bind_param("ssiiii", $title, $description, $duration, $attempts, $id, $teacher_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Assessment updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update assessment.']);
}

$stmt->close();
$conn->close();
