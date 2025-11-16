<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}

$student_id = (int)$_SESSION['user_id'];
$submission_id = (int)($_POST['submission_id'] ?? 0);

if (empty($submission_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid Submission ID.']);
    exit;
}

// First, get the submission details (to delete the file)
$stmt = $conn->prepare("SELECT submission_file, status FROM activity_submissions WHERE id = ? AND student_id = ?");
$stmt->bind_param("ii", $submission_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'error' => 'Submission not found or you do not have permission.']);
    exit;
}

$submission = $result->fetch_assoc();

// Block deletion if it's already graded
if ($submission['status'] === 'graded') {
    echo json_encode(['success' => false, 'error' => 'Cannot delete a graded submission.']);
    exit;
}

// Delete the file from the server
if (!empty($submission['submission_file']) && file_exists('../' . $submission['submission_file'])) {
    unlink('../' . $submission['submission_file']);
}

// Delete the record from the database
$stmt_delete = $conn->prepare("DELETE FROM activity_submissions WHERE id = ?");
$stmt_delete->bind_param("i", $submission_id);

if ($stmt_delete->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt_delete->error]);
}

$stmt->close();
$stmt_delete->close();
$conn->close();
