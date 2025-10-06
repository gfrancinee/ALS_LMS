<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Security: User must be a student and have an attempt "in_progress"
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student' || !isset($_SESSION['current_attempt_id'])) {
    header("Location: ../login.php");
    exit;
}

$attempt_id = $_SESSION['current_attempt_id'];
$student_id = $_SESSION['user_id'];
$answers = $_POST['answers'] ?? [];

$total_score = 0;
$total_items = 0;

if (!empty($answers)) {
    // ---  Calculate the score ---
    $score_stmt = $conn->prepare("SELECT correct_answer FROM questions WHERE id = ?");
    foreach ($answers as $question_id => $student_answer) {
        $total_items++;
        $score_stmt->bind_param("i", $question_id);
        $score_stmt->execute();
        $result = $score_stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (strtolower(trim($student_answer)) === strtolower(trim($row['correct_answer']))) {
                $total_score++;
            }
        }
    }
    $score_stmt->close();

    // --- Save each individual answer to the new table ---
    $save_answer_stmt = $conn->prepare(
        "INSERT INTO student_answers (attempt_id, question_id, student_answer) VALUES (?, ?, ?)"
    );
    foreach ($answers as $question_id => $student_answer) {
        $save_answer_stmt->bind_param("iis", $attempt_id, $question_id, $student_answer);
        $save_answer_stmt->execute();
    }
    $save_answer_stmt->close();

    // In ajax/submit_quiz.php, after a successful submission...

    // Get the teacher's ID (the creator of the strand)
    $strand_id = $_POST['strand_id'];
    $teacher_id = 0;
    $stmt = $conn->prepare("SELECT creator_id FROM learning_strands WHERE id = ?");
    $stmt->bind_param("i", $strand_id);
    $stmt->execute();
    $stmt->bind_result($teacher_id);
    $stmt->fetch();
    $stmt->close();

    // Create a notification for the teacher
    // Create a notification for the teacher
    $student_name = $_SESSION['user_fname'] ?? 'A student';
    $assessment_id = $_POST['assessment_id']; // GET THE ID FROM THE FORM

    $message = $student_name . " has submitted an assessment.";
    // Make sure this link is correct for your project
    $link = "../quiz/results.php?assessment_id=" . $assessment_id;

    if ($teacher_id > 0) {
        create_notification($conn, $teacher_id, $message, $link);
    }
}

// ---  Update the main attempt record with the final score ---
$update_attempt_stmt = $conn->prepare(
    "UPDATE quiz_attempts 
     SET score = ?, total_items = ?, status = 'completed', submitted_at = NOW() 
     WHERE id = ? AND student_id = ?"
);
$update_attempt_stmt->bind_param("iiii", $total_score, $total_items, $attempt_id, $student_id);
$update_attempt_stmt->execute();
$update_attempt_stmt->close();

// Clean up and redirect
unset($_SESSION['current_attempt_id']);
$conn->close();
header('Location: ../student/student.php?quiz_submitted=true');
exit();
