<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// --- Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// --- Get Data from Form ---
$assessment_id = $_POST['assessment_id'] ?? 0;
$question_text = trim($_POST['question_text'] ?? '');
$question_type = $_POST['question_type'] ?? '';
$teacher_id = $_SESSION['user_id'];

// --- Validation ---
if (empty($assessment_id) || empty($question_text) || empty($question_type)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

// Use a transaction to ensure all database operations succeed or fail together
$conn->begin_transaction();

try {
    // --- Step 1: Add the question to the central question_bank ---
    $stmt_question = $conn->prepare("INSERT INTO question_bank (teacher_id, question_text, question_type) VALUES (?, ?, ?)");
    $stmt_question->bind_param("iss", $teacher_id, $question_text, $question_type);
    $stmt_question->execute();
    $new_question_id = $conn->insert_id;
    $stmt_question->close();

    // --- Step 2: Add the answer options based on question type ---
    // CORRECTED TABLE NAME: 'question_options'
    $stmt_options = $conn->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");

    switch ($question_type) {
        case 'multiple_choice':
            $options = $_POST['options'] ?? [];
            $correct_option_index = $_POST['correct_option'] ?? -1;
            if (count($options) < 2 || $correct_option_index === -1) {
                throw new Exception("Multiple choice requires at least two options and a correct answer.");
            }

            foreach ($options as $index => $option_text) {
                if (empty(trim($option_text))) continue; // Skip empty options
                $is_correct = ($index == $correct_option_index) ? 1 : 0;
                $stmt_options->bind_param("isi", $new_question_id, $option_text, $is_correct);
                $stmt_options->execute();
            }
            break;

        case 'true_false':
            // CORRECTED VARIABLE NAME
            $correct_option_index = $_POST['tf_correct_option'] ?? -1;
            if ($correct_option_index === -1) {
                throw new Exception("True/False answer selection is missing.");
            }

            $options = ['True', 'False'];
            foreach ($options as $index => $option_text) {
                $is_correct = ($index == $correct_option_index) ? 1 : 0;
                $stmt_options->bind_param("isi", $new_question_id, $option_text, $is_correct);
                $stmt_options->execute();
            }
            break;

        case 'identification':
        case 'short_answer':
            // CORRECTED VARIABLE NAME
            $answer_text = trim($_POST['single_answer_text'] ?? '');

            // For 'identification', the answer is required.
            if ($question_type === 'identification' && empty($answer_text)) {
                throw new Exception("Identification questions require an answer.");
            }

            if (!empty($answer_text)) {
                $is_correct = 1;
                $stmt_options->bind_param("isi", $new_question_id, $answer_text, $is_correct);
                $stmt_options->execute();
            }
            break;

        case 'essay':
            // No options to save for essay type
            break;
    }
    $stmt_options->close();

    // --- Step 3: Link the question to this specific assessment ---
    $stmt_link = $conn->prepare("INSERT INTO assessment_questions (assessment_id, question_id) VALUES (?, ?)");
    $stmt_link->bind_param("ii", $assessment_id, $new_question_id);
    $stmt_link->execute();
    $stmt_link->close();

    // If everything was successful, commit the changes
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // If any step failed, roll back all changes
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
