<?php
// ajax/add_questions_to_assessment.php
session_start();
require_once '../includes/db.php'; // Adjust path if needed
header('Content-Type: application/json');

// --- Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$teacher_id = $_SESSION['user_id'];

// --- Get Data from POST request ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$assessment_id = filter_input(INPUT_POST, 'assessment_id', FILTER_VALIDATE_INT);
$question_ids_raw = $_POST['question_ids'] ?? []; // Expecting an array

// --- Validate Input ---
if (empty($assessment_id) || empty($question_ids_raw) || !is_array($question_ids_raw)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Invalid or missing data (assessment_id or question_ids array).']);
    exit;
}

// Sanitize question IDs to ensure they are integers
$question_ids = array_map('intval', $question_ids_raw);
$question_ids = array_filter($question_ids, function ($id) {
    return $id > 0;
}); // Remove zeros or invalid entries

if (empty($question_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid question IDs provided.']);
    exit;
}


// --- Verify Teacher Owns the Assessment ---
$stmt_check_owner = $conn->prepare("SELECT id FROM assessments WHERE id = ? AND teacher_id = ?");
if ($stmt_check_owner === false) {
    error_log("Prepare failed (check owner): " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error checking assessment ownership.']);
    exit;
}
$stmt_check_owner->bind_param("ii", $assessment_id, $teacher_id);
$stmt_check_owner->execute();
if ($stmt_check_owner->get_result()->num_rows === 0) {
    http_response_code(403); // Forbidden (or 404 Not Found)
    echo json_encode(['success' => false, 'error' => 'Assessment not found or permission denied.']);
    $stmt_check_owner->close();
    exit;
}
$stmt_check_owner->close();


// --- Database Transaction ---
$conn->begin_transaction();
$successfully_added_ids = []; // Store IDs that were actually inserted
$error_occurred = false;
$error_message = 'An unknown error occurred during the transaction.';

try {
    // 1. Prepare statement to link questions
    // Using INSERT IGNORE prevents errors if a link already exists
    $sql_link = "INSERT IGNORE INTO assessment_questions (assessment_id, question_id, points) VALUES (?, ?, ?)";
    $stmt_link = $conn->prepare($sql_link);
    if ($stmt_link === false) throw new Exception("Prepare failed (link insert): " . $conn->error);

    // Default points (fetch from bank or use a standard default)
    // Here we'll just use a default of 1 for simplicity, matching your table structure default
    $default_points = 1;

    // 2. Loop and Insert links
    foreach ($question_ids as $question_id) {
        $stmt_link->bind_param("iii", $assessment_id, $question_id, $default_points);
        if (!$stmt_link->execute()) {
            // Log error but continue trying others? Or stop? We'll stop here.
            throw new Exception("Error linking question ID {$question_id}: " . $stmt_link->error);
        }
        // Check if a row was actually inserted (affected_rows > 0)
        if ($stmt_link->affected_rows > 0) {
            $successfully_added_ids[] = $question_id; // Add to list for fetching details later
        }
    }
    $stmt_link->close();

    // 3. Commit the transaction
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback(); // Rollback changes on error
    $error_occurred = true;
    $error_message = $e->getMessage();
    error_log("Add questions to assessment error: " . $error_message); // Log detailed error
}

// --- Prepare Response ---
if (!$error_occurred && !empty($successfully_added_ids)) {
    // Fetch details ONLY for the questions successfully added in this transaction
    $new_questions_html = '';
    $placeholders = implode(',', array_fill(0, count($successfully_added_ids), '?'));
    $types = str_repeat('i', count($successfully_added_ids));

    $sql_details = "SELECT id, question_text, question_type, grading_type, max_points
                    FROM question_bank
                    WHERE id IN ({$placeholders})";
    $stmt_details = $conn->prepare($sql_details);

    if ($stmt_details) {
        $stmt_details->bind_param($types, ...$successfully_added_ids);
        $stmt_details->execute();
        $result_details = $stmt_details->get_result();
        $added_questions_details = $result_details->fetch_all(MYSQLI_ASSOC);
        $stmt_details->close();

        // Get the current count of questions *before* these were added, for numbering
        $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM assessment_questions WHERE assessment_id = ?");
        $stmt_count->bind_param("i", $assessment_id);
        $stmt_count->execute();
        // Calculate starting number based on total count MINUS how many we just added
        $total_now = $stmt_count->get_result()->fetch_assoc()['count'];
        $q_num_start = $total_now - count($successfully_added_ids) + 1;
        $stmt_count->close();


        // --- Generate HTML for the newly added questions ---
        foreach ($added_questions_details as $index => $question) {
            $q_num = $q_num_start + $index;
            $question_text_html = nl2br(htmlspecialchars($question['question_text']));
            $question_type_html = str_replace('_', ' ', ucfirst($question['question_type']));
            $grading_type_html = ucfirst($question['grading_type']);
            // Use max_points from bank as default display
            $points_html = $question['max_points'] . 'pt' . ($question['max_points'] > 1 ? 's' : '');

            // Ensure this HTML structure matches exactly what's used on manage_assessment.php
            $new_questions_html .= <<<HTML
            <div class="bg-light rounded p-3 mb-2 question-card" data-question-id="{$question['id']}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="fw-bold mb-1">
                            Question {$q_num}:
                            <span class="badge bg-secondary fw-normal ms-2">{$question_type_html}</span>
                            <span class="badge bg-info fw-normal ms-1">{$grading_type_html} Grading ({$points_html})</span>
                        </p>
                        <p class="mb-0 question-text-display">{$question_text_html}</p>
                    </div>
                    <div class="actions-container flex-shrink-0 ms-3 d-flex">
                        <button class="btn btn-action-icon edit edit-question-btn me-1" title="Edit Question" data-bs-toggle="modal" data-bs-target="#editQuestionModal" data-question-id="{$question['id']}">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn btn-action-icon delete delete-question-btn" title="Remove Question" data-bs-toggle="modal" data-bs-target="#deleteQuestionModal" data-question-id="{$question['id']}" data-assessment-id="{$assessment_id}">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                </div>
            </div>
HTML;
        }

        echo json_encode(['success' => true, 'addedQuestionsHtml' => $new_questions_html]);
    } else {
        // Error fetching details after successful insert
        error_log("Failed to prepare statement for fetching added question details: " . $conn->error);
        http_response_code(500);
        // Send success=true but maybe empty HTML or a specific message?
        // It's tricky because the data IS saved, but we can't show it dynamically.
        // Let's send an error so the user knows something went slightly wrong.
        echo json_encode(['success' => false, 'error' => 'Questions added, but failed to retrieve details for display. Please reload the page.']);
    }
} elseif (!$error_occurred && empty($successfully_added_ids)) {
    // No errors, but no questions were added (likely duplicates ignored)
    echo json_encode(['success' => true, 'addedQuestionsHtml' => '', 'message' => 'Selected questions were already present in the assessment.']);
} else {
    // An error occurred during the transaction
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => $error_message]);
}

$conn->close();
