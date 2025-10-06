<?php
session_start();
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
    // --- PART 1: Calculate the score ---
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

    // --- PART 2: Save each individual answer to the new table ---
    $save_answer_stmt = $conn->prepare(
        "INSERT INTO student_answers (attempt_id, question_id, student_answer) VALUES (?, ?, ?)"
    );
    foreach ($answers as $question_id => $student_answer) {
        $save_answer_stmt->bind_param("iis", $attempt_id, $question_id, $student_answer);
        $save_answer_stmt->execute();
    }
    $save_answer_stmt->close();
}

// --- PART 3: Update the main attempt record with the final score ---
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
header("Location: results.php?attempt_id=" . $attempt_id);
exit;
