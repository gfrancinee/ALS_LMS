<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security Check: Only for logged-in teachers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// 1. Reads from $_POST to match the JavaScript
$assessment_id = $_POST['assessment_id'] ?? 0;
$teacher_id = $_SESSION['user_id'];

if (empty($assessment_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Assessment ID is required.']);
    exit;
}

// 2. Updates the 'is_open' column and automatically toggles the value (0 to 1, or 1 to 0)
$stmt = $conn->prepare("UPDATE assessments SET is_open = !is_open WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $assessment_id, $teacher_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not update status or assessment not found.']);
}

$stmt->close();
$conn->close();
