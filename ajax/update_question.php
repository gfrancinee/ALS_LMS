<?php
// ajax/update_question.php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// --- Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$teacher_id = $_SESSION['user_id'];

// --- Get & Validate Data ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['question_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request or missing Question ID.']);
    exit;
}

$question_id = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
$question_text = $_POST['question_text'] ?? ''; // Allow empty text? Add validation if needed.
$grading_type = $_POST['grading_type'] ?? 'automatic';
$max_points = filter_input(INPUT_POST, 'max_points', FILTER_VALIDATE_INT) ?? 1;

if (!$question_id || empty($question_text)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input data. Question text cannot be empty.']);
    exit;
}

// --- Database Transaction ---
$conn->begin_transaction();

try {
    // 1. Verify Ownership & Get Question Type (Type cannot be changed)
    $stmt_check = $conn->prepare("SELECT question_type FROM question_bank WHERE id = ? AND teacher_id = ?");
    if ($stmt_check === false) throw new Exception("Prepare failed (check): " . $conn->error);
    $stmt_check->bind_param("ii", $question_id, $teacher_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $question_data = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$question_data) {
        throw new Exception("Question not found or permission denied.");
    }
    $question_type = $question_data['question_type']; // Get the original type

    // Adjust grading type if essay (should always be manual)
    if ($question_type == 'essay') {
        $grading_type = 'manual';
    }
    // Adjust max_points if automatic (should always be 1)
    if ($grading_type == 'automatic') {
        $max_points = 1;
    }


    // 2. Update question_bank table
    $sql_qb = "UPDATE question_bank SET question_text = ?, grading_type = ?, max_points = ? WHERE id = ? AND teacher_id = ?";
    $stmt_qb = $conn->prepare($sql_qb);
    if ($stmt_qb === false) throw new Exception("Prepare failed (update qb): " . $conn->error);
    $stmt_qb->bind_param("ssiii", $question_text, $grading_type, $max_points, $question_id, $teacher_id);
    $stmt_qb->execute();
    if ($stmt_qb->affected_rows === 0 && $stmt_qb->errno) { // Check for actual errors vs no change needed
        throw new Exception("Failed to update question bank: " . $stmt_qb->error);
    }
    $stmt_qb->close();

    // 3. Update question_options (Handle different types)
    $submitted_option_ids = $_POST['edit_option_ids'] ?? []; // Get IDs of options submitted in the form
    $existing_option_ids = []; // To store IDs currently in DB for this question

    // Prepare statements for option updates/inserts/deletes
    $sql_opt_update = "UPDATE question_options SET option_text = ?, is_correct = ? WHERE id = ? AND question_id = ?";
    $stmt_opt_update = $conn->prepare($sql_opt_update);
    if ($stmt_opt_update === false) throw new Exception("Prepare failed (opt update): " . $conn->error);

    $sql_opt_insert = "INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
    $stmt_opt_insert = $conn->prepare($sql_opt_insert);
    if ($stmt_opt_insert === false) throw new Exception("Prepare failed (opt insert): " . $conn->error);

    $sql_opt_delete = "DELETE FROM question_options WHERE id = ? AND question_id = ?";
    $stmt_opt_delete = $conn->prepare($sql_opt_delete);
    if ($stmt_opt_delete === false) throw new Exception("Prepare failed (opt delete): " . $conn->error);

    // Fetch existing option IDs for comparison
    $stmt_fetch_ids = $conn->prepare("SELECT id FROM question_options WHERE question_id = ?");
    if ($stmt_fetch_ids === false) throw new Exception("Prepare failed (fetch ids): " . $conn->error);
    $stmt_fetch_ids->bind_param("i", $question_id);
    $stmt_fetch_ids->execute();
    $result_ids = $stmt_fetch_ids->get_result();
    while ($row = $result_ids->fetch_assoc()) {
        $existing_option_ids[] = $row['id'];
    }
    $stmt_fetch_ids->close();


    // Process options based on type
    if ($question_type == 'multiple_choice') {
        $options_text = $_POST['edit_options'] ?? []; // Array [option_id => text]
        $correct_option_id = $_POST['edit_correct_option'] ?? null;

        foreach ($submitted_option_ids as $opt_id) {
            if (isset($options_text[$opt_id]) && !empty($options_text[$opt_id])) {
                $is_correct = ($opt_id == $correct_option_id) ? 1 : 0;
                $stmt_opt_update->bind_param("siii", $options_text[$opt_id], $is_correct, $opt_id, $question_id);
                $stmt_opt_update->execute();
            }
        }
    } elseif ($question_type == 'true_false') {
        $correct_option_id = $_POST['edit_tf_correct_option'] ?? null;
        foreach ($submitted_option_ids as $opt_id) { // Should be only 2 IDs
            $is_correct = ($opt_id == $correct_option_id) ? 1 : 0;
            // Option text doesn't change for T/F, only correctness
            $stmt_opt_update_tf = $conn->prepare("UPDATE question_options SET is_correct = ? WHERE id = ? AND question_id = ?");
            if ($stmt_opt_update_tf === false) throw new Exception("Prepare failed (tf update): " . $conn->error);
            $stmt_opt_update_tf->bind_param("iii", $is_correct, $opt_id, $question_id);
            $stmt_opt_update_tf->execute();
            $stmt_opt_update_tf->close();
        }
    } elseif ($question_type == 'identification' || $question_type == 'short_answer') {
        $answer_text = $_POST['edit_single_answer_text'] ?? '';
        $option_id_to_update = $submitted_option_ids[0] ?? 'new'; // Should only be one ID or 'new'

        // Delete existing incorrect answers if switching to automatic with a new answer
        if ($grading_type == 'automatic' && !empty($answer_text)) {
            $stmt_delete_incorrect = $conn->prepare("DELETE FROM question_options WHERE question_id = ? AND is_correct = 0");
            if ($stmt_delete_incorrect === false) throw new Exception("Prepare failed (delete incorrect): " . $conn->error);
            $stmt_delete_incorrect->bind_param("i", $question_id);
            $stmt_delete_incorrect->execute();
            $stmt_delete_incorrect->close();
        }


        if ($grading_type == 'automatic' && !empty($answer_text)) {
            $is_correct = 1;
            if ($option_id_to_update !== 'new' && is_numeric($option_id_to_update)) {
                // Update existing correct answer
                $stmt_opt_update->bind_param("siii", $answer_text, $is_correct, $option_id_to_update, $question_id);
                $stmt_opt_update->execute();
            } else {
                // Insert new correct answer (if 'new' or ID was invalid)
                // Need to make sure there isn't already a correct answer before inserting
                $stmt_check_correct = $conn->prepare("SELECT id FROM question_options WHERE question_id = ? AND is_correct = 1");
                if ($stmt_check_correct === false) throw new Exception("Prepare failed (check correct): " . $conn->error);
                $stmt_check_correct->bind_param("i", $question_id);
                $stmt_check_correct->execute();
                if ($stmt_check_correct->get_result()->num_rows === 0) {
                    $stmt_opt_insert->bind_param("isi", $question_id, $answer_text, $is_correct);
                    $stmt_opt_insert->execute();
                }
                $stmt_check_correct->close();
            }
        } elseif ($grading_type == 'manual' || empty($answer_text)) {
            // If grading is manual OR answer text is empty, ensure NO options are marked as correct
            $stmt_clear_correct = $conn->prepare("UPDATE question_options SET is_correct = 0 WHERE question_id = ?");
            if ($stmt_clear_correct === false) throw new Exception("Prepare failed (clear correct): " . $conn->error);
            $stmt_clear_correct->bind_param("i", $question_id);
            $stmt_clear_correct->execute();
            $stmt_clear_correct->close();
        }
    }

    // Close prepared statements for options
    $stmt_opt_update->close();
    $stmt_opt_insert->close();
    $stmt_opt_delete->close(); // Not used currently, but good practice

    // 4. Commit transaction
    $conn->commit();

    // 5. Prepare data for JSON response (only essential updated fields)
    $updated_question_data = [
        'id' => $question_id,
        'question_text' => $question_text, // Return the saved text
        'grading_type' => $grading_type,
        'max_points' => $max_points
    ];

    // 6. Send success response
    echo json_encode(['success' => true, 'updatedQuestion' => $updated_question_data]);
} catch (Exception $e) {
    // If anything fails, roll back
    $conn->rollback();
    http_response_code(500);
    error_log("Update Question Error: " . $e->getMessage()); // Log detailed error
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
