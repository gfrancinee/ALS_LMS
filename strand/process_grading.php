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

// --- [THIS IS THE FIX] ---
// We only require the $attempt_id. The $points_array can be empty (if the teacher gives 0 points).
if (empty($attempt_id)) {
    // --- [END OF FIX] ---
    $_SESSION['error_message'] = "Invalid or missing data submitted.";
    header("Location: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "view_submissions.php"));
    exit;
}

// --- Verify Teacher Ownership ---
$assessment_id = null;
$student_id = null; // <-- For notification
$total_items = 0;   // <-- For notification
$assessment_title = ''; // <-- For notification

$stmt_check = $conn->prepare(
    // --- [THIS IS THE FIX] ---
    // We need to get more info for the notification step
    "SELECT a.id as assessment_id, a.title as assessment_title, qa.student_id, qa.total_items
     FROM quiz_attempts qa
     JOIN assessments a ON qa.assessment_id = a.id
     WHERE qa.id = ? AND a.teacher_id = ?"
    // --- [END OF FIX] ---
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
// --- [THIS IS THE FIX] ---
// Get all the new data
$attempt_data = $result_check->fetch_assoc();
$assessment_id = $attempt_data['assessment_id'];
$assessment_title = $attempt_data['assessment_title'];
$student_id = $attempt_data['student_id'];
$total_items = (int)$attempt_data['total_items'];
// --- [END OF FIX] ---
$stmt_check->close();


// --- Database Transaction ---
$conn->begin_transaction();
$error_occurred = false;
$error_message = 'An unknown error occurred while saving grades.';

try {
    // 1. Prepare statement to update points_awarded
    $sql_update_points = "UPDATE student_answers sa
                          JOIN question_bank qb ON sa.question_id = qb.id
                          SET sa.points_awarded = ?, sa.is_correct = ?
                          WHERE sa.id = ? AND sa.quiz_attempt_id = ? AND (qb.grading_type = 'manual' OR qb.question_type = 'essay')";

    $stmt_update_points = $conn->prepare($sql_update_points);
    if ($stmt_update_points === false) throw new Exception("Prepare failed (update points): " . $conn->error);


    // 2. Loop through submitted points and update
    foreach ($points_array as $student_answer_id => $points) {
        $sa_id = filter_var($student_answer_id, FILTER_VALIDATE_INT);

        // Make sure points are valid, default to 0 if not
        $points_value = (is_numeric($points) && $points >= 0) ? (float)$points : 0;
        $is_correct_flag = ($points_value > 0) ? 1 : 0;

        // Bind and execute the update
        $stmt_update_points->bind_param("diii", $points_value, $is_correct_flag, $sa_id, $attempt_id);
        if (!$stmt_update_points->execute()) {
            throw new Exception("Error updating points for answer ID {$sa_id}: " . $stmt_update_points->error);
        }
    }
    $stmt_update_points->close();


    // 3. Recalculate the total score
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
    $result_recalculate = $stmt_recalculate->get_result();
    $new_total_score = $result_recalculate->fetch_assoc()['total_score'] ?? 0;
    $stmt_recalculate->close();

    // 4. Update the total score AND STATUS in quiz_attempts
    // --- [THIS IS THE FIX] ---
    $sql_update_total = "UPDATE quiz_attempts SET score = ?, status = 'submitted' WHERE id = ?";
    // --- [END OF FIX] ---
    $stmt_update_total = $conn->prepare($sql_update_total);
    if ($stmt_update_total === false) throw new Exception("Prepare failed (update total): " . $conn->error);
    $stmt_update_total->bind_param("di", $new_total_score, $attempt_id); // 'd' for double/float score
    $stmt_update_total->execute();
    $stmt_update_total->close();

    // --- [NEW NOTIFICATION CODE] ---
    // 5. Send notification to the student
    $notification_type = 'assessment_graded';
    $notification_message = "Your grade for '" . htmlspecialchars($assessment_title) . "' is ready. You scored $new_total_score / $total_items.";
    $notification_link = "student/quiz_results.php?attempt_id=" . $attempt_id;

    create_notification($conn, $student_id, $notification_type, $assessment_id, $notification_message, $notification_link);
    // --- [END OF NEW CODE] ---

    // 6. Commit the transaction
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
