<?php
session_start();
header('Content-Type: application/json');

// Use a reliable path to your includes folder.
// IMPORTANT: Change '/ALS_LMS/' to match your project's root folder name if it is different.
$db_path = $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/includes/db.php';

if (!file_exists($db_path)) {
    echo json_encode(['success' => false, 'message' => 'Database connection file not found.']);
    exit;
}
require_once $db_path;

// --- The rest of your script ---
// Security checks
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$assessment_id = $data['assessment_id'] ?? null;
$student_id = $_SESSION['user_id'];

if (!$assessment_id) {
    echo json_encode(['success' => false, 'message' => 'Assessment ID not provided.']);
    exit;
}

// Check for remaining attempts
$stmt = $conn->prepare("SELECT max_attempts FROM assessments WHERE id = ?");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT COUNT(*) as attempt_count FROM quiz_attempts WHERE assessment_id = ? AND student_id = ?");
$stmt->bind_param("ii", $assessment_id, $student_id);
$stmt->execute();
$attempts = $stmt->get_result()->fetch_assoc();

if ($attempts['attempt_count'] >= $assessment['max_attempts']) {
    echo json_encode(['success' => false, 'message' => 'You have no attempts left.']);
    exit;
}

// Create the new attempt
$stmt = $conn->prepare("INSERT INTO quiz_attempts (assessment_id, student_id, score, status, started_at) VALUES (?, ?, 0, 'in_progress', NOW())");
$stmt->bind_param("ii", $assessment_id, $student_id);

if ($stmt->execute()) {
    $_SESSION['current_attempt_id'] = $stmt->insert_id;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: Could not start the quiz.']);
}

$stmt->close();
$conn->close();
