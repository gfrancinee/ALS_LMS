<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security check: ensure user is a logged-in teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$assessment_id = $_GET['assessment_id'] ?? null;
if (!$assessment_id) {
    echo json_encode(['error' => 'Assessment ID is required.']);
    exit;
}

// Fetch all questions and their options for the assessment
$stmt = $conn->prepare("
    SELECT q.id, q.question_text, q.question_type, q.correct_answer, o.option_text
    FROM questions q
    LEFT JOIN question_options o ON q.id = o.question_id
    WHERE q.assessment_id = ?
    ORDER BY q.id, o.id
");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();

$questions = [];
while ($row = $result->fetch_assoc()) {
    $qid = $row['id'];
    if (!isset($questions[$qid])) {
        $questions[$qid] = [
            'id' => $qid,
            'question_text' => $row['question_text'],
            'question_type' => $row['question_type'],
            'correct_answer' => $row['correct_answer'],
            'options' => []
        ];
    }
    if ($row['option_text']) {
        $questions[$qid]['options'][] = $row['option_text'];
    }
}

// Return the questions as a JSON object
echo json_encode(array_values($questions));
$stmt->close();
$conn->close();
