<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// --- SECURITY CHECK: Ensure user is a logged-in student ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    die("Unauthorized access.");
}
$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['fname'] ?? 'A student'; // This line is correct

// --- GET DATA FROM FORM ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    die("Invalid request method.");
}

$assessment_id = filter_input(INPUT_POST, 'assessment_id', FILTER_VALIDATE_INT);
$quiz_attempt_id = filter_input(INPUT_POST, 'quiz_attempt_id', FILTER_VALIDATE_INT);
$submitted_answers = $_POST['answers'] ?? []; // Array [question_id => answer]

// --- VALIDATE INPUT ---
if (empty($assessment_id) || empty($quiz_attempt_id) || empty($submitted_answers)) {
    $_SESSION['error_message'] = "Invalid submission data.";
    header("Location: take_assessment.php?id=" . $assessment_id);
    exit;
}

// --- VERIFY ATTEMPT OWNERSHIP & STATUS ---
$stmt_check = $conn->prepare("SELECT id FROM quiz_attempts WHERE id = ? AND student_id = ? AND status = 'in_progress'");
if ($stmt_check === false) {
    die("Prepare failed (check attempt): " . $conn->error);
}
$stmt_check->bind_param("ii", $quiz_attempt_id, $student_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows === 0) {
    // Attempt not found, not owned, or already submitted
    $_SESSION['error_message'] = "Invalid attempt or this assessment has already been submitted.";
    header("Location: take_assessment.php?id=" . $assessment_id);
    exit;
}
$stmt_check->close();

// --- START DATABASE TRANSACTION ---
$conn->begin_transaction();
$total_score_earned = 0;    // Student's calculated score (for auto-graded items)
$total_points_possible = 0;
$has_manual_grading = false;

try {
    // 1. Fetch ALL Questions, Grading Info, and Correct Answers
    $sql_questions = "
        SELECT 
            qb.id, 
            qb.question_type, 
            qb.grading_type, 
            qb.max_points,
            GROUP_CONCAT(qo.id SEPARATOR ',') as correct_option_ids,
            GROUP_CONCAT(LOWER(qo.option_text) SEPARATOR '|') as correct_option_texts
        FROM question_bank qb
        JOIN assessment_questions aq ON qb.id = aq.question_id
        LEFT JOIN question_options qo ON qb.id = qo.question_id AND qo.is_correct = 1
        WHERE aq.assessment_id = ?
        GROUP BY qb.id
    ";
    $stmt_questions = $conn->prepare($sql_questions);
    if ($stmt_questions === false) throw new Exception("Prepare failed (fetch questions): " . $conn->error);
    $stmt_questions->bind_param("i", $assessment_id);
    $stmt_questions->execute();
    $questions_result = $stmt_questions->get_result();
    $questions_data = [];
    while ($row = $questions_result->fetch_assoc()) {
        $questions_data[$row['id']] = $row;
    }
    $stmt_questions->close();

    // 2. Prepare statement to insert student's answers
    $sql_insert_answer = "INSERT INTO student_answers 
                            (quiz_attempt_id, question_id, answer_text, is_correct, points_awarded) 
                          VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert_answer);
    if ($stmt_insert === false) throw new Exception("Prepare failed (insert answers): " . $conn->error);

    // 3. Loop through questions, grade, and insert answers
    foreach ($questions_data as $question_id => $question) {
        $student_answer_text = isset($submitted_answers[$question_id]) ? htmlspecialchars(trim($submitted_answers[$question_id]), ENT_QUOTES, 'UTF-8') : null;
        $is_correct = 0;
        $points_awarded = 0;

        $total_points_possible += $question['max_points'];

        if ($question['grading_type'] == 'manual') {
            $has_manual_grading = true;
        }

        if ($student_answer_text !== null) {
            // --- AUTO-GRADING LOGIC ---
            if ($question['grading_type'] == 'automatic') {
                if ($question['question_type'] == 'multiple_choice' || $question['question_type'] == 'true_false') {
                    $correct_option_ids = explode(',', $question['correct_option_ids']);
                    if (in_array($student_answer_text, $correct_option_ids)) {
                        $is_correct = 1;
                        $points_awarded = $question['max_points'];
                    }
                } elseif ($question['question_type'] == 'identification' || $question['question_type'] == 'short_answer') {
                    $correct_texts = explode('|', $question['correct_option_texts']);
                    if (in_array(strtolower($student_answer_text), $correct_texts)) {
                        $is_correct = 1;
                        $points_awarded = $question['max_points'];
                    }
                }
                $total_score_earned += $points_awarded;
            }
            // --- MANUAL GRADING LOGIC ---
            elseif ($question['grading_type'] == 'manual') {
                $points_awarded = null; // Set to NULL to mark as "pending review"
                $is_correct = 0;
            }

            $stmt_insert->bind_param("iisid", $quiz_attempt_id, $question_id, $student_answer_text, $is_correct, $points_awarded);
            $stmt_insert->execute();
        } else {
            // Student did not answer this question
            $empty_answer = "";
            $is_correct = 0;
            $points_awarded = 0;
            $stmt_insert->bind_param("iisid", $quiz_attempt_id, $question_id, $empty_answer, $is_correct, $points_awarded);
            $stmt_insert->execute();
        }
    }
    $stmt_insert->close();

    // 4. Finalize and update the quiz_attempt record
    $end_time = date("Y-m-d H:i:s");
    $status = $has_manual_grading ? 'pending_grading' : 'submitted';

    $sql_update_attempt = "UPDATE quiz_attempts SET 
                            submitted_at = ?, 
                            status = ?, 
                            score = ?, 
                            total_items = ? 
                           WHERE id = ? AND student_id = ?";
    $stmt_update_attempt = $conn->prepare($sql_update_attempt);
    if ($stmt_update_attempt === false) throw new Exception("Prepare failed (update attempt): " . $conn->error);

    $stmt_update_attempt->bind_param("ssiiii", $end_time, $status, $total_score_earned, $total_points_possible, $quiz_attempt_id, $student_id);
    $stmt_update_attempt->execute();
    $stmt_update_attempt->close();

    // 5. Commit the transaction
    $conn->commit();

    // --- [NOTIFICATION BLOCK] ---
    try {
        // Get the teacher_id and assessment title directly from the assessments table
        $stmt_info = $conn->prepare(
            "SELECT title, teacher_id 
             FROM assessments
             WHERE id = ?"
        );
        if ($stmt_info) {
            $stmt_info->bind_param("i", $assessment_id);
            $stmt_info->execute();
            $info_result = $stmt_info->get_result()->fetch_assoc();

            $teacher_id = $info_result['teacher_id'] ?? null;
            $assessment_title = $info_result['title'] ?? 'Untitled Assessment';
            $stmt_info->close();

            if ($teacher_id) {
                // Prepare notification details
                $recipient_id = $teacher_id;
                $type = 'submit_assessment';
                $message = htmlspecialchars($student_name) . " submitted an attempt for '" . htmlspecialchars($assessment_title) . "'.";
                $link = "teacher/view_attempt.php?attempt_id=" . $quiz_attempt_id;

                // --- THIS IS THE FIX ---
                // We pass the UNIQUE $quiz_attempt_id as the related_id.
                // This forces 'create_notification' to make a new entry every time.
                create_notification($conn, $recipient_id, $type, $quiz_attempt_id, $message, $link);
                // --- END OF FIX ---
            }
        }
    } catch (Exception $e) {
        // Log notification error, but don't stop the student
        error_log("Notification Error: " . $e->getMessage());
    }
    // --- [END OF NOTIFICATION BLOCK] ---

    // Redirect to the results page, as specified in your old code
    $_SESSION['success_message'] = "Assessment submitted successfully!";
    header("Location: quiz_results.php?attempt_id=" . $quiz_attempt_id);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    error_log("Submit Error: " . $e->getMessage());
    $_SESSION['error_message'] = "A critical error occurred while submitting your assessment. Please contact your teacher.";
    header("Location: take_assessment.php?id=" . $assessment_id);
    exit;
}

$conn->close();
