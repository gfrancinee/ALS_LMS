<?php
session_start();
require_once '../../includes/db.php'; // Correct path from admin/LS-oversight/
require_once '../../includes/functions.php'; // Correct path

// --- SECURITY CHECK: Ensure user is a logged-in Admin ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die("Unauthorized access.");
}

// --- VALIDATE REQUEST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Invalid request method.");
}

$attempt_id = filter_input(INPUT_POST, 'attempt_id', FILTER_VALIDATE_INT);
$points = $_POST['points'] ?? []; // Array of [student_answer_id => score]

if (empty($attempt_id) || empty($points)) {
    $_SESSION['error_message'] = "No grading data was submitted.";
    header("Location: review_answers.php?attempt_id=" . $attempt_id);
    exit;
}

// --- START DATABASE TRANSACTION ---
$conn->begin_transaction();

try {
    // 1. Prepare statement to update individual answer scores
    // We also update `is_correct` to 1 if the user gets full points, 0 otherwise
    $stmt_update_answer = $conn->prepare("
        UPDATE student_answers sa
        JOIN question_bank qb ON sa.question_id = qb.id
        SET 
            sa.points_awarded = ?,
            sa.is_correct = (CASE WHEN ? >= qb.max_points THEN 1 ELSE 0 END)
        WHERE sa.id = ?
    ");
    if ($stmt_update_answer === false) {
        throw new Exception("Prepare failed (update answers): " . $conn->error);
    }

    foreach ($points as $student_answer_id => $score) {
        $sa_id = (int)$student_answer_id;
        $score_awarded = (float)$score;

        // Bind params: (points_awarded, score_for_check, student_answer_id)
        $stmt_update_answer->bind_param("ddi", $score_awarded, $score_awarded, $sa_id);
        $stmt_update_answer->execute();
    }
    $stmt_update_answer->close();

    // 2. Recalculate the total score for the entire attempt
    $stmt_sum = $conn->prepare("
        SELECT SUM(points_awarded) 
        FROM student_answers 
        WHERE quiz_attempt_id = ?
    ");
    if ($stmt_sum === false) {
        throw new Exception("Prepare failed (sum score): " . $conn->error);
    }
    $stmt_sum->bind_param("i", $attempt_id);
    $stmt_sum->execute();
    $new_total_score = 0;
    $stmt_sum->bind_result($new_total_score);
    $stmt_sum->fetch();
    $stmt_sum->close();

    // 3. Update the main quiz_attempts table with the new total score
    //    and set the status to 'submitted' (or 'graded') to remove the 'pending' status.
    $final_status = 'submitted'; // This will hide the "Start Review" button
    $stmt_update_attempt = $conn->prepare("
        UPDATE quiz_attempts 
        SET score = ?, status = ? 
        WHERE id = ?
    ");
    if ($stmt_update_attempt === false) {
        throw new Exception("Prepare failed (update attempt): " . $conn->error);
    }
    $stmt_update_attempt->bind_param("dsi", $new_total_score, $final_status, $attempt_id);
    $stmt_update_attempt->execute();
    $stmt_update_attempt->close();

    // 4. Commit all changes
    $conn->commit();

    $_SESSION['success_message'] = "Grades have been saved successfully!";
    // Redirect back to the view page
    header("Location: view_single_attempt.php?id=" . $attempt_id);
    exit;
} catch (Exception $e) {
    // If anything fails, roll back all changes
    $conn->rollback();
    error_log("Admin Grading Error: " . $e->getMessage());
    $_SESSION['error_message'] = "A critical error occurred while saving grades. No changes were made.";
    header("Location: review_answers.php?attempt_id=" . $attempt_id);
    exit;
}

$conn->close();
