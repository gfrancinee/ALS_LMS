<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// 1. Check if user is a TEACHER
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$teacher_id = (int)$_SESSION['user_id'];

// 2. Get Form Data
$submission_id = (int)($_POST['submission_id'] ?? 0);
$score = (int)($_POST['score'] ?? 0);
$feedback = trim($_POST['feedback'] ?? '');

if (empty($submission_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid submission ID.']);
    exit;
}

// 3. Verify teacher owns this submission's assessment
$stmt_verify = $conn->prepare(
    "SELECT a.id 
     FROM assessments a
     JOIN activity_submissions s ON a.id = s.assessment_id
     WHERE s.id = ? AND a.teacher_id = ?"
);
$stmt_verify->bind_param("ii", $submission_id, $teacher_id);
$stmt_verify->execute();
$result_verify = $stmt_verify->get_result();
if ($result_verify->num_rows == 0) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to grade this submission.']);
    exit;
}
$stmt_verify->close();

// 4. Update the submission with the grade
$stmt = $conn->prepare(
    "UPDATE activity_submissions 
     SET score = ?, feedback = ?, status = 'graded' 
     WHERE id = ?"
);
$stmt->bind_param("isi", $score, $feedback, $submission_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
