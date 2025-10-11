<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get Data
$question_id = $_POST['question_id'] ?? 0;
$question_text = trim($_POST['question_text'] ?? '');

if (empty($question_id) || empty($question_text)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

// Start a transaction
$conn->begin_transaction();

try {
    // Step 1: Update the main question text in the question_bank
    $stmt1 = $conn->prepare("UPDATE question_bank SET question_text = ? WHERE id = ?");
    $stmt1->bind_param("si", $question_text, $question_id);
    $stmt1->execute();
    $stmt1->close();

    // Step 2: Delete all existing options for this question. This is the simplest way to handle updates.
    $stmt2 = $conn->prepare("DELETE FROM question_options WHERE question_id = ?");
    $stmt2->bind_param("i", $question_id);
    $stmt2->execute();
    $stmt2->close();

    // Step 3: Re-insert the new options, similar to the add_question script
    $question_type = $_POST['question_type'] ?? '';
    $stmt3 = $conn->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");

    if ($question_type === 'multiple_choice') {
        $options = $_POST['options'] ?? [];
        $correct_option_index = $_POST['correct_option'] ?? -1;
        foreach ($options as $index => $option_text) {
            if (!empty($option_text)) {
                $is_correct = ($index == $correct_option_index) ? 1 : 0;
                $stmt3->bind_param("isi", $question_id, $option_text, $is_correct);
                $stmt3->execute();
            }
        }
    } elseif ($question_type === 'true_false') {
        $options = ['True', 'False'];
        $correct_option_index = $_POST['tf_correct_option'] ?? -1;
        foreach ($options as $index => $option_text) {
            $is_correct = ($index == $correct_option_index) ? 1 : 0;
            $stmt3->bind_param("isi", $question_id, $option_text, $is_correct);
            $stmt3->execute();
        }
    } elseif (in_array($question_type, ['identification', 'short_answer'])) {
        $answer = trim($_POST['single_answer_text'] ?? '');
        if (!empty($answer)) {
            $is_correct = 1;
            $stmt3->bind_param("isi", $question_id, $answer, $is_correct);
            $stmt3->execute();
        }
    }
    $stmt3->close();

    // If all steps were successful, commit the changes
    $conn->commit();
    echo json_encode(['success' => true, 'updated_text' => htmlspecialchars($question_text)]);
} catch (Exception $e) {
    // If any step failed, roll back all changes
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'A database error occurred: ' . $e->getMessage()]);
}

$conn->close();
