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

$assessment_id = $_GET['id'] ?? 0;
if (empty($assessment_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Assessment ID is required.']);
    exit;
}

$teacher_id = $_SESSION['user_id'];

// Fetch assessment details, ensuring the teacher owns it
$stmt = $conn->prepare("SELECT * FROM assessments WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $assessment_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($assessment = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'data' => $assessment]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Assessment not found or you do not have permission to edit it.']);
}

$stmt->close();
$conn->close();
