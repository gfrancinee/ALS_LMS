<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Reads the JSON data sent by the new JavaScript
$data = json_decode(file_get_contents('php://input'), true);

$strand_id = $data['strand_id'] ?? null;
$teacher_id = $_SESSION['user_id'];
$title = trim($data['title'] ?? '');
$description = trim($data['description'] ?? '');
$duration = (int)($data['duration'] ?? 60);
$attempts = (int)($data['attempts'] ?? 1);

if (empty($title) || !$strand_id) {
    echo json_encode(['success' => false, 'message' => 'Title and strand are required.']);
    exit;
}

// Inserts into the new database columns
$stmt = $conn->prepare(
    "INSERT INTO assessments (strand_id, teacher_id, title, description, duration_minutes, max_attempts) VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("iissii", $strand_id, $teacher_id, $title, $description, $duration, $attempts);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Assessment created successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create assessment.']);
}

$stmt->close();
$conn->close();
