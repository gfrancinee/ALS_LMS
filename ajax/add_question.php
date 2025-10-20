<?php
// ajax/add_question.php
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['assessment_id']) || empty($_POST['question_text']) || empty($_POST['question_type'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request data.']);
    exit;
}

$assessment_id = filter_input(INPUT_POST, 'assessment_id', FILTER_VALIDATE_INT);
$question_text = $_POST['question_text']; // Will be bound as string, XSS handled on output
$question_type = $_POST['question_type'];
$grading_type = $_POST['grading_type'] ?? 'automatic';
$max_points = filter_input(INPUT_POST, 'max_points', FILTER_VALIDATE_INT) ?? 1;

// --- Database Transaction ---
$conn->begin_transaction();

try {
    // 1. Insert into question_bank
    $sql_qb = "INSERT INTO question_bank (teacher_id, question_text, question_type, grading_type, max_points) VALUES (?, ?, ?, ?, ?)";
    $stmt_qb = $conn->prepare($sql_qb);
    if ($stmt_qb === false) throw new Exception("Prepare failed (question_bank): " . $conn->error);

    $stmt_qb->bind_param("isssi", $teacher_id, $question_text, $question_type, $grading_type, $max_points);
    $stmt_qb->execute();
    $new_question_id = $stmt_qb->insert_id; // Get the ID of the new question
    $stmt_qb->close();

    if ($new_question_id == 0) throw new Exception("Failed to create question in bank.");

    // 2. Insert into question_options (if applicable)
    $sql_opt = "INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
    $stmt_opt = $conn->prepare($sql_opt);
    if ($stmt_opt === false) throw new Exception("Prepare failed (question_options): " . $conn->error);

    if ($question_type == 'multiple_choice') {
        $options = $_POST['options'] ?? [];
        $correct_option_index = $_POST['correct_option'] ?? -1;
        foreach ($options as $index => $option_text) {
            if (!empty($option_text)) { // Only save non-empty options
                $is_correct = ($index == $correct_option_index) ? 1 : 0;
                $stmt_opt->bind_param("isi", $new_question_id, $option_text, $is_correct);
                $stmt_opt->execute();
            }
        }
    } elseif ($question_type == 'true_false') {
        // Use the index value from the form (e.g., '0' for True, '1' for False)
        $options = $_POST['tf_options'] ?? ['True', 'False'];
        $correct_option_index = $_POST['tf_correct_option'] ?? -1; // This should be '0' or '1'
        foreach ($options as $index => $option_text) {
            $is_correct = ($index == $correct_option_index) ? 1 : 0;
            $stmt_opt->bind_param("isi", $new_question_id, $option_text, $is_correct);
            $stmt_opt->execute();
        }
    } elseif ($question_type == 'identification' || $question_type == 'short_answer') {
        $answer_text = $_POST['single_answer_text'] ?? '';
        if ($grading_type == 'automatic' && !empty($answer_text)) {
            $is_correct = 1;
            $stmt_opt->bind_param("isi", $new_question_id, $answer_text, $is_correct);
            $stmt_opt->execute();
        }
    }
    // 'essay' types have no options saved by default
    $stmt_opt->close();


    // 3. Link question to the assessment
    $sql_link = "INSERT INTO assessment_questions (assessment_id, question_id, points) VALUES (?, ?, ?)";
    $stmt_link = $conn->prepare($sql_link);
    if ($stmt_link === false) throw new Exception("Prepare failed (assessment_questions): " . $conn->error);

    // Use max_points when linking
    $stmt_link->bind_param("iii", $assessment_id, $new_question_id, $max_points);
    $stmt_link->execute();
    $stmt_link->close();

    // 4. Commit transaction
    $conn->commit();

    // 5. Get count of existing questions to set the new question number
    $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM assessment_questions WHERE assessment_id = ?");
    $stmt_count->bind_param("i", $assessment_id);
    $stmt_count->execute();
    $q_num = $stmt_count->get_result()->fetch_assoc()['count']; // This will be the number for the new question
    $stmt_count->close();

    // 6. Build the HTML response
    $question_text_html = nl2br(htmlspecialchars($question_text));
    $question_type_html = str_replace('_', ' ', ucfirst($question_type));
    $grading_type_html = ucfirst($grading_type);
    $points_html = $max_points . 'pt' . ($max_points > 1 ? 's' : '');

    // --- THIS IS THE FIX ---
    // The badge classes now match your manage_assessment.php loop
    // (bg-secondary -> text-secondary)
    // (bg-info -> text-success)
    $new_questions_html = <<<HTML
        <div class="bg-light rounded p-3 mb-2 question-card" data-question-id="{$new_question_id}">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="fw-bold mb-1">
                        Question {$q_num}:
                        <span class="badge text-secondary fw-normal ms-2 badge-question-type">{$question_type_html}</span>
                        <span class="badge text-success fw-normal ms-1 badge-grading-info">{$grading_type_html} Grading ({$points_html})</span>
                    </p>
                    <p class="mb-0 question-text-display">{$question_text_html}</p>
                </div>
                <div class="actions-container flex-shrink-0 ms-3 d-flex">
                    <button class="btn btn-action-icon edit edit-question-btn me-1" title="Edit Question" data-bs-toggle="modal" data-bs-target="#editQuestionModal" data-question-id="{$new_question_id}">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-action-icon delete delete-question-btn" title="Remove Question" data-bs-toggle="modal" data-bs-target="#deleteQuestionModal" data-question-id="{$new_question_id}" data-assessment-id="{$assessment_id}">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        </div>
HTML;
    // --- END OF FIX ---

    // 7. Send the correct JSON response
    echo json_encode(['success' => true, 'newQuestionHtml' => $new_questions_html]);
} catch (Exception $e) {
    // If anything fails, roll back
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
