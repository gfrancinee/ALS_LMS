<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';

// 1. Get the JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);

$teacher_id = $_SESSION['user_id'] ?? null;
$assessment_id = $data['assessment_id'] ?? null;
$strand_id = $data['strand_id'] ?? null;
$questions = $data['questions'] ?? [];

// 2. Validation
if (!$teacher_id) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
    exit;
}
if (!$assessment_id || !$strand_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required data.']);
    exit;
}

$conn->begin_transaction();

try {
    // First, delete all existing questions and their options for this assessment
    $stmt = $conn->prepare("DELETE FROM questions WHERE assessment_id = ?");
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $stmt->close();

    if (!empty($questions)) {
        // 3. Prepare database statements with CORRECT column names
        $question_insert_stmt = $conn->prepare(
            "INSERT INTO questions (assessment_id, strand_id, question_text, question_type, correct_answer) VALUES (?, ?, ?, ?, ?)"
        );
        $option_insert_stmt = $conn->prepare(
            "INSERT INTO question_options (question_id, option_key, option_text) VALUES (?, ?, ?)"
        );

        // 4. Loop through each question and save it
        foreach ($questions as $question) {
            $question_text = $question['text'];
            $question_type = $question['type'];
            // This is the variable name from the JavaScript
            $correct_answer = $question['answer'];

            $question_insert_stmt->bind_param(
                "iisss",
                $assessment_id,
                $strand_id,
                $question_text,
                $question_type,
                $correct_answer
            );
            $question_insert_stmt->execute();

            if ($question_type === 'mcq') {
                $new_question_id = $conn->insert_id;
                $option_keys = ['a', 'b', 'c', 'd'];

                foreach ($question['options'] as $index => $option_text) {
                    if (isset($option_keys[$index])) {
                        $option_key = $option_keys[$index];
                        $option_insert_stmt->bind_param(
                            "iss",
                            $new_question_id,
                            $option_key,
                            $option_text
                        );
                        $option_insert_stmt->execute();
                    }
                }
            }
        }
        $question_insert_stmt->close();
        $option_insert_stmt->close();
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Questions saved successfully!']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
