<?php
// ajax/get_question_details.php (Final Version)
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
// Add logging to see exactly what's happening
error_log("GET_DETAILS RAW GET: " . print_r($_GET, true));

// Check specifically for 'id', ensure it's numeric and positive
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || (int)$_GET['id'] <= 0) {
    // Log the failure point
    error_log("GET_DETAILS VALIDATION FAILED. ID Value: '" . ($_GET['id'] ?? 'Not Set') . "'");
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Invalid or missing Question ID parameter (?id=...).']);
    exit; // Stop execution if validation fails
}

// Validation passed, cast to integer
$question_id = (int)$_GET['id'];
error_log("GET_DETAILS VALIDATED ID: " . $question_id); // Log the validated ID

// --- Proceed with fetching data ---
$response_data = ['question' => null, 'options' => []];
$error_message = null;

try {
    // 1. Fetch Question Details (including grading info and ownership check)
    $sql_q = "SELECT id, question_text, question_type, grading_type, max_points
              FROM question_bank
              WHERE id = ? AND teacher_id = ?";
    $stmt_q = $conn->prepare($sql_q);
    if ($stmt_q === false) throw new Exception("Prepare failed (question): " . $conn->error);

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

    // 2. Fetch Options (including option ID)
    if (in_array($question_details['question_type'], ['multiple_choice', 'true_false', 'identification', 'short_answer'])) {
        $sql_o = "SELECT id, option_text, is_correct FROM question_options WHERE question_id = ?";
        $stmt_o = $conn->prepare($sql_o);
        if ($stmt_o === false) throw new Exception("Prepare failed (options): " . $conn->error);

        $stmt_o->bind_param("i", $question_id);
        $stmt_o->execute();
        $result_o = $stmt_o->get_result();
        $response_data['options'] = $result_o->fetch_all(MYSQLI_ASSOC);
        $stmt_o->close();
    }
} catch (Exception $e) {
    if (http_response_code() < 400) http_response_code(500); // Default to 500 if not already set
    $error_message = $e->getMessage();
    error_log("Error in get_question_details.php: " . $e->getMessage()); // Log the actual exception
}

$conn->close();

// --- Send Final Response ---
if ($error_message === null && $response_data['question'] !== null) {
    // Success: Send data nested under 'data' key
    echo json_encode(['success' => true, 'data' => $response_data]);
} else {
    // Failure: Send error message
    // Ensure error message is set if question wasn't found but no exception occurred
    if ($error_message === null) $error_message = 'Failed to retrieve question data.';
    echo json_encode(['success' => false, 'error' => $error_message]);
}
