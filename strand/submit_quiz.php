<?php
session_start();
require_once '../includes/db.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../student/student.php");
    exit;
}

// --- GET DATA FROM FORM ---
$assessment_id = $_POST['assessment_id'] ?? 0;
$quiz_attempt_id = $_POST['quiz_attempt_id'] ?? 0;
$student_answers = $_POST['answers'] ?? [];
$student_id = $_SESSION['user_id'];

if (empty($assessment_id) || empty($quiz_attempt_id)) {
    die("Error: Missing required assessment information.");
}

// --- GRADING LOGIC ---
$score = 0;
$total_items = count($student_answers);

// Fetch all correct answers for this assessment's questions in one query
$correct_answers = [];
$sql_correct = "
    SELECT qo.question_id, qo.id as correct_option_id
    FROM question_options qo
    WHERE qo.is_correct = 1 AND qo.question_id IN (
        SELECT question_id FROM assessment_questions WHERE assessment_id = ?
    )
";
$stmt_correct = $conn->prepare($sql_correct);
$stmt_correct->bind_param("i", $assessment_id);
$stmt_correct->execute();
$result_correct = $stmt_correct->get_result();
while ($row = $result_correct->fetch_assoc()) {
    $correct_answers[$row['question_id']] = $row['correct_option_id'];
}
$stmt_correct->close();

// --- THIS IS THE FIX (PART 1): The INSERT query now includes the student_id column ---
$save_answer_stmt = $conn->prepare(
    "INSERT INTO student_answers (student_id, quiz_attempt_id, question_id, selected_option_id, answer_text, is_correct) VALUES (?, ?, ?, ?, ?, ?)"
);

// Process each answer submitted by the student
foreach ($student_answers as $question_id => $answer) {
    $selected_option_id = null;
    $answer_text = null;
    $is_correct = 0;

    if (is_numeric($answer)) { // This is a multiple-choice or true/false answer
        $selected_option_id = $answer;
        if (isset($correct_answers[$question_id]) && $correct_answers[$question_id] == $selected_option_id) {
            $score++;
            $is_correct = 1;
        }
    } else { // This is a text-based answer
        $answer_text = trim($answer);
        $is_correct = 0; // Text answers require manual grading
    }

    // --- THIS IS THE FIX (PART 2): The bind_param now includes the integer 'i' for student_id ---
    $save_answer_stmt->bind_param("iiiisi", $student_id, $quiz_attempt_id, $question_id, $selected_option_id, $answer_text, $is_correct);
    $save_answer_stmt->execute();
}
$save_answer_stmt->close();

// --- FINAL STEP: UPDATE THE QUIZ ATTEMPT RECORD ---
$submitted_at = date("Y-m-d H:i:s");
$status = 'completed';
$update_attempt_stmt = $conn->prepare(
    "UPDATE quiz_attempts SET submitted_at = ?, score = ?, total_items = ?, status = ? WHERE id = ? AND student_id = ?"
);
$update_attempt_stmt->bind_param("siisii", $submitted_at, $score, $total_items, $status, $quiz_attempt_id, $student_id);
$update_attempt_stmt->execute();
$update_attempt_stmt->close();
$conn->close();

// --- REDIRECT TO THE RESULTS PAGE ---
header("Location: quiz_results.php?attempt_id=" . $quiz_attempt_id);
exit;
