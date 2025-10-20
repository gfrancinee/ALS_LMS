<?php
session_start();
require_once '../includes/db.php'; // Adjust path if needed
require_once '../includes/functions.php'; // Adjust path if needed

// --- Security Check: Ensure user is a logged-in teacher ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    // Redirect or show error, maybe set a session message
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
    // Redirect back to the grading page if possible, otherwise to submissions
    header("Location: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "view_submissions.php"));
    exit;
}

// --- Verify Teacher Ownership of the Assessment linked to the Attempt ---
$assessment_id = null; // We need this to redirect back later
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
    header("Location: index.php"); // Redirect somewhere sensible
    exit;
}
$assessment_id = $result_check->fetch_assoc()['assessment_id']; // Get assessment ID for redirect
$stmt_check->close();


// --- Database Transaction ---
$conn->begin_transaction();
$error_occurred = false;
$error_message = 'An unknown error occurred while saving grades.';

try {
    // 1. Prepare statement to update points_awarded in student_answers
    $sql_update_points = "UPDATE student_answers sa
                          JOIN question_bank qb ON sa.question_id = qb.id
                          SET sa.points_awarded = ?
                          WHERE sa.id = ? AND sa.quiz_attempt_id = ? AND qb.grading_type = 'manual'"; // Only update manual ones
    $stmt_update_points = $conn->prepare($sql_update_points);
    if ($stmt_update_points === false) throw new Exception("Prepare failed (update points): " . $conn->error);


    // 2. Loop through submitted points and update
    foreach ($points_array as $student_answer_id => $points) {
        $sa_id = filter_var($student_answer_id, FILTER_VALIDATE_INT);
        // Validate points: Check if numeric, non-negative.
        // We might also fetch max_points here for stricter validation, but rely on form limits for now.
        if ($sa_id && is_numeric($points) && $points >= 0) {
            // Convert points to float or decimal if needed (e.g., allow 0.5)
            $points_value = (float)$points;

            // Bind and execute the update for this specific student answer ID
            $stmt_update_points->bind_param("dii", $points_value, $sa_id, $attempt_id); // 'd' for double/float
            if (!$stmt_update_points->execute()) {
                // Log error but maybe continue? Or stop? Let's stop.
                throw new Exception("Error updating points for answer ID {$sa_id}: " . $stmt_update_points->error);
            }
        } else {
            // Log invalid input, but continue processing others
            error_log("Invalid points data received: AnswerID={$student_answer_id}, Points={$points}");
        }
    }
    $stmt_update_points->close();


    // 3. Recalculate the total score for the attempt
    // Sum points_awarded for manual items AND max_points for correctly answered automatic items
    $sql_recalculate = "
        SELECT SUM(
            CASE
                WHEN qb.grading_type = 'manual' THEN COALESCE(sa.points_awarded, 0)
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
    $result_recalculate = $stmt_recalculate->get_result();
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
    error_log("Process Grading Error: " . $error_message); // Log detailed error
    $_SESSION['error_message'] = "Error saving grades: " . $error_message; // Show detailed error? Be careful with this.
    // $_SESSION['error_message'] = "An error occurred while saving grades. Please try again."; // More user-friendly
}

$conn->close();

// --- Redirect back to the grading page ---
// Include attempt_id in redirect URL
header("Location: grade_submission.php?attempt_id=" . $attempt_id);
exit;
