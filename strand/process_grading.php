<?php
session_start();
require_once '../includes/db.php'; // Adjust path if needed
require_once '../includes/functions.php'; // Adjust path if needed

// --- Security Check: Ensure user is a logged-in teacher ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['error_message'] = "Unauthorized access.";
    header("Location: ../login.php");
    exit;
}
$teacher_id = $_SESSION['user_id'];

// --- Get Data from POST request ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: index.php"); // Redirect somewhere sensible
    exit;
}

$attempt_id = filter_input(INPUT_POST, 'attempt_id', FILTER_VALIDATE_INT);
$points_array = $_POST['points'] ?? []; // Expecting an array [student_answer_id => points]

// --- Validate Input ---
if (empty($attempt_id) || empty($points_array) || !is_array($points_array)) {
    $_SESSION['error_message'] = "Invalid or missing data submitted.";
    header("Location: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "view_submissions.php"));
    exit;
}

// --- Verify Teacher Ownership ---
$assessment_id = null;
$stmt_check = $conn->prepare(
    "SELECT a.id as assessment_id
     FROM quiz_attempts qa
     JOIN assessments a ON qa.assessment_id = a.id
     WHERE qa.id = ? AND a.teacher_id = ?"
);
if ($stmt_check === false) {
    die("Prepare failed (check ownership): " . $conn->error);
}
$stmt_check->bind_param("ii", $attempt_id, $teacher_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
if ($result_check->num_rows === 0) {
    $_SESSION['error_message'] = "Attempt not found or permission denied.";
    header("Location: index.php");
    exit;
}
$assessment_id = $result_check->fetch_assoc()['assessment_id'];
$stmt_check->close();


// --- Database Transaction ---
$conn->begin_transaction();
$error_occurred = false;
$error_message = 'An unknown error occurred while saving grades.';

try {
    // 1. Prepare statement to update points_awarded in student_answers

    // *** FIX #1: Update 'points_awarded' AND 'is_correct' ***
    // *** FIX #2: The WHERE clause now matches the form's logic: (grading_type = 'manual' OR question_type = 'essay') ***

    $sql_update_points = "UPDATE student_answers sa
                          JOIN question_bank qb ON sa.question_id = qb.id
                          SET sa.points_awarded = ?, sa.is_correct = ?
                          WHERE sa.id = ? AND sa.quiz_attempt_id = ? AND (qb.grading_type = 'manual' OR qb.question_type = 'essay')";

    $stmt_update_points = $conn->prepare($sql_update_points);
    if ($stmt_update_points === false) throw new Exception("Prepare failed (update points): " . $conn->error);


    // 2. Loop through submitted points and update
    foreach ($points_array as $student_answer_id => $points) {
        $sa_id = filter_var($student_answer_id, FILTER_VALIDATE_INT);

        if ($sa_id && is_numeric($points) && $points >= 0) {
            $points_value = (float)$points;

            // *** FIX #3: Determine the 'is_correct' flag based on points ***
            // Any points > 0 means the answer is (at least partially) correct.
            $is_correct_flag = ($points_value > 0) ? 1 : 0;

            // Bind and execute the update
            $stmt_update_points->bind_param("diii", $points_value, $is_correct_flag, $sa_id, $attempt_id); // 'd' for double, 'i' for int
            if (!$stmt_update_points->execute()) {
                throw new Exception("Error updating points for answer ID {$sa_id}: " . $stmt_update_points->error);
            }
        } else {
            error_log("Invalid points data received: AnswerID={$student_answer_id}, Points={$points}");
        }
    }
    $stmt_update_points->close();


    // 3. Recalculate the total score for the attempt

    // *** FIX #4: Make sure the recalculation query ALSO includes essay points ***
    // 3. Recalculate the total score for the attempt

    $sql_recalculate = "
        SELECT SUM(
            CASE
                WHEN qb.grading_type = 'manual' THEN COALESCE(sa.points_awarded, 0)
                WHEN qb.question_type = 'essay' THEN COALESCE(sa.points_awarded, 0)
                WHEN qb.grading_type = 'automatic' AND sa.is_correct = 1 THEN qb.max_points
                ELSE 0
            END
        ) as total_score
        FROM student_answers sa
        JOIN question_bank qb ON sa.question_id = qb.id
        WHERE sa.quiz_attempt_id = ?
    ";

    $stmt_recalculate = $conn->prepare($sql_recalculate);
    if ($stmt_recalculate === false) throw new Exception("Prepare failed (recalculate): " . $conn->error);

    $stmt_recalculate->bind_param("i", $attempt_id);
    $stmt_recalculate->execute();

    // --- THIS IS THE MISSING LINE ---
    $result_recalculate = $stmt_recalculate->get_result();
    // --- END FIX ---

    $new_total_score = $result_recalculate->fetch_assoc()['total_score'] ?? 0;
    $stmt_recalculate->close();

    // 4. Update the total score in quiz_attempts
    $sql_update_total = "UPDATE quiz_attempts SET score = ? WHERE id = ?";
    $stmt_update_total = $conn->prepare($sql_update_total);
    if ($stmt_update_total === false) throw new Exception("Prepare failed (update total): " . $conn->error);
    $stmt_update_total->bind_param("di", $new_total_score, $attempt_id); // 'd' for double/float score
    $stmt_update_total->execute();
    $stmt_update_total->close();

    // 5. Commit the transaction
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback(); // Rollback changes on error
    $error_occurred = true;
    $error_message = $e->getMessage();
    error_log("Process Grading Error: " . $error_message);
    $_SESSION['error_message'] = "An error occurred while saving grades. Please try again.";
}

$conn->close();

// --- Redirect back to the grading page ---
header("Location: grade_submission.php?attempt_id=" . $attempt_id);
exit;
