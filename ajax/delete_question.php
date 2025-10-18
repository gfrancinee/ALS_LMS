<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// --- Security Check: User must be a logged-in teacher ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// --- Get Data ---
// Use filter_input for better security/validation
$question_id = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
$assessment_id = filter_input(INPUT_POST, 'assessment_id', FILTER_VALIDATE_INT);
$teacher_id = $_SESSION['user_id'];

// --- Validate Data ---
if (empty($question_id) || empty($assessment_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Invalid or missing data provided.']);
    exit;
}

// --- Prepare the DELETE statement ---
// This query is crucial:
// 1. It targets the 'assessment_questions' link table.
// 2. It joins 'assessments' to verify the 'teacher_id'.
// 3. It deletes only the specific link for the given assessment and question.
$stmt = $conn->prepare(
    "DELETE aq FROM assessment_questions AS aq
     JOIN assessments a ON aq.assessment_id = a.id
     WHERE aq.assessment_id = ? AND aq.question_id = ? AND a.teacher_id = ?"
);

// Check if statement preparation failed
if ($stmt === false) {
    // Log detailed error for server admin, show generic error to user
    error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'Database error preparing statement.']);
    exit;
}

// --- Bind parameters and Execute ---
$stmt->bind_param("iii", $assessment_id, $question_id, $teacher_id);

if ($stmt->execute()) {
    // Check if any row was actually affected/deleted
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Question removed from assessment.']);
    } else {
        // No rows deleted - likely means the link didn't exist or teacher didn't own assessment
        // From the user's perspective, the question is not linked, so maybe still 'success'
        // Or you could return an error indicating permission issue/not found
        echo json_encode(['success' => true, 'message' => 'Question link not found or permission denied.']);
    }
} else {
    // Log detailed execution error
    error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'Failed to remove question link from assessment.']);
}

// --- Close resources ---
$stmt->close();
$conn->close();
