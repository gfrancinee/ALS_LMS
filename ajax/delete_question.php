<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security Check: User must be a logged-in teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$question_id = $_POST['question_id'] ?? 0;
$assessment_id = $_POST['assessment_id'] ?? 0;
$teacher_id = $_SESSION['user_id'];

if (empty($question_id) || empty($assessment_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data provided.']);
    exit;
}

// This query is very important. It only deletes the *link* in the assessment_questions table.
// It does NOT delete the question from the central question_bank.
// It also joins the assessments table to make sure the teacher owns the assessment.
$stmt = $conn->prepare(
    "DELETE aq FROM assessment_questions AS aq
     JOIN assessments a ON aq.assessment_id = a.id
     WHERE aq.assessment_id = ? AND aq.question_id = ? AND a.teacher_id = ?"
);
$stmt->bind_param("iii", $assessment_id, $question_id, $teacher_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete question from assessment.']);
}

$stmt->close();
$conn->close();
