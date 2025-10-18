<?php
// ajax/get_question_details.php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// --- Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$teacher_id = $_SESSION['user_id'];

// --- Get & Validate ID ---
// Changed to 'id' to match the JS fetch URL: `?id=${questionId}`
$question_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$question_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Invalid or missing Question ID.']);
    exit;
}

$response_data = ['question' => null, 'options' => []];
$error_message = null;

try {
    // 1. Fetch Question Details
    // Added teacher_id check for ownership and included grading fields
    $sql_q = "SELECT id, question_text, question_type, grading_type, max_points
              FROM question_bank
              WHERE id = ? AND teacher_id = ?";
    $stmt_q = $conn->prepare($sql_q);
    if ($stmt_q === false) {
        throw new Exception("Prepare failed (question): " . $conn->error);
    }

    $stmt_q->bind_param("ii", $question_id, $teacher_id);
    $stmt_q->execute();
    $result_q = $stmt_q->get_result();
    $question_details = $result_q->fetch_assoc();
    $stmt_q->close();

    if (!$question_details) {
        http_response_code(404); // Not Found
        throw new Exception("Question not found or permission denied.");
    }
    $response_data['question'] = $question_details;

    // 2. Fetch Options (if applicable)
    // Only fetch if the question type might have options
    if (in_array($question_details['question_type'], ['multiple_choice', 'true_false', 'identification', 'short_answer'])) {
        // Added 'id' to the select statement for options
        $sql_o = "SELECT id, option_text, is_correct FROM question_options WHERE question_id = ?";
        $stmt_o = $conn->prepare($sql_o);
        if ($stmt_o === false) {
            throw new Exception("Prepare failed (options): " . $conn->error);
        }

        $stmt_o->bind_param("i", $question_id);
        $stmt_o->execute();
        $result_o = $stmt_o->get_result();
        // Fetch all options into the array
        $response_data['options'] = $result_o->fetch_all(MYSQLI_ASSOC);
        $stmt_o->close();
    }
} catch (Exception $e) {
    // Use the existing http_response_code if set (e.g., 404), otherwise default to 500
    if (http_response_code() < 400) {
        http_response_code(500); // Internal Server Error
    }
    $error_message = $e->getMessage();
    // Log detailed error for server admin if needed
    // error_log("Error in get_question_details.php: " . $e->getMessage());
}

$conn->close();

// --- Send Response ---
if ($error_message === null) {
    // Nest the results under a 'data' key as expected by the JS
    echo json_encode(['success' => true, 'data' => $response_data]);
} else {
    echo json_encode(['success' => false, 'error' => $error_message]);
}
