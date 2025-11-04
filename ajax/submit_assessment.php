<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// --- 1. VALIDATION ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if (!isset($_POST['assessment_id']) || !isset($_POST['answers'])) {
    echo json_encode(['success' => false, 'message' => 'Missing assessment data.']);
    exit;
}

$student_id = $_SESSION['user_id'];
$assessment_id = (int)$_POST['assessment_id'];
$answers = $_POST['answers']; // This is an array of answers [question_id => answer]

$conn->begin_transaction();

try {
    // --- 2. CREATE THE ASSESSMENT ATTEMPT & SAVE ANSWERS ---

    // Create the main attempt record
    $stmt_attempt = $conn->prepare("INSERT INTO student_assessments (assessment_id, student_id, status, submitted_at) VALUES (?, ?, 'submitted', NOW())");
    $stmt_attempt->bind_param("ii", $assessment_id, $student_id);
    $stmt_attempt->execute();
    $student_assessment_id = $conn->insert_id; // The new attempt ID
    $stmt_attempt->close();

    $total_score = 0;
    $max_score = 0;
    $requires_manual_grading = false;

    // Prepare statement to save each answer
    $stmt_save_answer = $conn->prepare("
        INSERT INTO student_answers (student_assessment_id, question_id, answer_text, selected_option_id, score, is_correct)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($answers as $question_id => $student_answer) {
        // Get question details (type, grading, points)
        $stmt_q = $conn->prepare("SELECT question_type, grading_type, max_points FROM question_bank WHERE id = ?");
        $stmt_q->bind_param("i", $question_id);
        $stmt_q->execute();
        $question = $stmt_q->get_result()->fetch_assoc();
        $stmt_q->close();

        if (!$question) continue; // Skip if question not found

        $max_points = (int)$question['max_points'];
        $max_score += $max_points;

        // Initialize variables for this loop
        $answer_text = null;
        $selected_option_id = null;
        $question_score = 0;
        $is_correct = 0; // 0 = wrong/pending, 1 = correct

        if ($question['question_type'] == 'multiple_choice' || $question['question_type'] == 'true_false') {
            $selected_option_id = (int)$student_answer;

            // Auto-grade: Check if this option is correct
            $stmt_opt = $conn->prepare("SELECT is_correct, option_text FROM question_options WHERE id = ? AND question_id = ?");
            $stmt_opt->bind_param("ii", $selected_option_id, $question_id);
            $stmt_opt->execute();
            $option = $stmt_opt->get_result()->fetch_assoc();
            $stmt_opt->close();

            if ($option) {
                $answer_text = $option['option_text']; // Store "True" or "Option A"
                if ($option['is_correct'] == 1) {
                    $is_correct = 1;
                    $question_score = $max_points;
                }
            }
        } else if (($question['question_type'] == 'identification' || $question['question_type'] == 'short_answer') && $question['grading_type'] == 'automatic') {
            $answer_text = trim($student_answer);

            // Auto-grade: Check against the correct answer text
            $stmt_opt = $conn->prepare("SELECT option_text FROM question_options WHERE question_id = ? AND is_correct = 1 LIMIT 1");
            $stmt_opt->bind_param("i", $question_id);
            $stmt_opt->execute();
            $correct_option = $stmt_opt->get_result()->fetch_assoc();
            $stmt_opt->close();

            if ($correct_option && strcasecmp($answer_text, $correct_option['option_text']) == 0) {
                $is_correct = 1;
                $question_score = $max_points;
            }
        } else {
            // This is for 'essay' or 'short_answer' set to 'manual'
            $answer_text = trim($student_answer);
            $requires_manual_grading = true;
            $is_correct = 0; // Pending manual review
            $question_score = 0; // Pending manual review
        }

        // Save the processed answer
        $stmt_save_answer->bind_param("iissii", $student_assessment_id, $question_id, $answer_text, $selected_option_id, $question_score, $is_correct);
        $stmt_save_answer->execute();

        $total_score += $question_score;
    }
    $stmt_save_answer->close();

    // Update the main attempt with the final score
    $final_status = $requires_manual_grading ? 'submitted' : 'graded'; // If no essays, it's fully graded
    $stmt_update_attempt = $conn->prepare("UPDATE student_assessments SET score = ?, max_score = ?, status = ? WHERE id = ?");
    $stmt_update_attempt->bind_param("iisi", $total_score, $max_score, $final_status, $student_assessment_id);
    $stmt_update_attempt->execute();
    $stmt_update_attempt->close();

    // --- 3. NEW NOTIFICATION LOGIC ---
    // (This is the code you sent, placed after answers are saved)

    // Get the teacher_id for this assessment
    $stmt_teacher = $conn->prepare("SELECT teacher_id FROM assessments WHERE id = ?");
    $stmt_teacher->bind_param("i", $assessment_id);
    $stmt_teacher->execute();
    $teacher_id = $stmt_teacher->get_result()->fetch_assoc()['teacher_id'];
    $stmt_teacher->close();

    if ($teacher_id) {
        // Define the notification details
        $notification_type = 'assessment_attempt';
        // This link will take the teacher to the grading page
        $notification_link = "grade_assessment.php?id=" . $assessment_id;

        // This one query does all the work:
        $stmt_notify = $conn->prepare("
            INSERT INTO notifications (user_id, type, related_id, count, link, is_read)
            VALUES (?, ?, ?, 1, ?, 0)
            ON DUPLICATE KEY UPDATE
                count = count + 1,
                is_read = 0,
                updated_at = NOW()
        ");
        $stmt_notify->bind_param("isss", $teacher_id, $notification_type, $assessment_id, $notification_link);
        $stmt_notify->execute();
        $stmt_notify->close();
    }

    // --- 4. FINISH AND RETURN SUCCESS ---
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Assessment submitted successfully!']);
} catch (Exception $e) {
    $conn->rollback();
    // Log the error for debugging
    error_log('Error in submit_assessment.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

$conn->close();
