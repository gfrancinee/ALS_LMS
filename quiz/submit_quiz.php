<?php
session_start();
require_once '../includes/db.php';

// Security: User must be a student and have an attempt "in_progress"
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student' || !isset($_SESSION['current_attempt_id'])) {
    // Redirect if the session is invalid
    header("Location: ../login.php");
    exit;
}

// Get the attempt ID from the session, which was set when the quiz started
$attempt_id = $_SESSION['current_attempt_id'];
$student_id = $_SESSION['user_id'];
$assessment_id = $_POST['assessment_id'] ?? 0;
$answers = $_POST['answers'] ?? [];

$total_score = 0;
$total_items = 0;

if (!empty($answers)) {
    // Prepare a statement to get the correct answer for each question
    $stmt = $conn->prepare("SELECT correct_answer FROM questions WHERE id = ?");

    foreach ($answers as $question_id => $student_answer) {
        $total_items++;
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $correct_answer = $row['correct_answer'];
            // Compare answers (case-insensitive and trimmed)
            if (strtolower(trim($student_answer)) === strtolower(trim($correct_answer))) {
                $total_score++;
            }
        }
    }
    $stmt->close();
}

// UPDATE the existing attempt that was created when the student started the quiz
$update_stmt = $conn->prepare(
    "UPDATE quiz_attempts 
     SET score = ?, total_items = ?, status = 'completed', submitted_at = NOW() 
     WHERE id = ? AND student_id = ?"
);
$update_stmt->bind_param("iiii", $total_score, $total_items, $attempt_id, $student_id);
$update_stmt->execute();
$update_stmt->close();

// Important: Unset the session variable so it can't be reused
unset($_SESSION['current_attempt_id']);
$conn->close();

// Redirect to the results page, passing the attempt ID
header("Location: results.php?attempt_id=" . $attempt_id);
exit;
