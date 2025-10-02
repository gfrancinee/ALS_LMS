<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

$assessment_id = $data['assessment_id'] ?? null;
$strand_id = $data['strand_id'] ?? null;
$questions = $data['questions'] ?? [];

if (!$assessment_id || !$strand_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data provided.']);
    exit;
}

$conn->begin_transaction();

try {
    $delete_stmt = $conn->prepare("DELETE FROM questions WHERE assessment_id = ?");
    $delete_stmt->bind_param("i", $assessment_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    if (!empty($questions)) {
        $question_insert_stmt = $conn->prepare(
            "INSERT INTO questions (assessment_id, strand_id, question_text, question_type, correct_answer) 
             VALUES (?, ?, ?, ?, ?)"
        );

        $option_insert_stmt = $conn->prepare(
            "INSERT INTO question_options (question_id, option_key, option_text) 
             VALUES (?, ?, ?)"
        );

        foreach ($questions as $question) {
            $question_insert_stmt->bind_param(
                "iisss",
                $assessment_id,
                $strand_id,
                $question['text'],
                $question['type'],
                $question['answer']
            );
            $question_insert_stmt->execute();

            if ($question['type'] === 'mcq') {
                $new_question_id = $conn->insert_id;
                $option_keys = ['a', 'b', 'c', 'd'];
                foreach ($question['options'] as $index => $option_text) {
                    if (isset($option_keys[$index])) {
                        $option_insert_stmt->bind_param(
                            "iss",
                            $new_question_id,
                            $option_keys[$index],
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
