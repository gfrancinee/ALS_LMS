<?php
// /ajax/get_recommendations.php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

// --- Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$student_id = $_SESSION['user_id'];

$recommendations = [];
$recommended_ids = [];
$wrong_question_ids_data = [];

// 1. Find the 10 most recent wrong answers for this student across all their quizzes.
$sql_wrong = "
    SELECT DISTINCT sa.question_id, q.strand_id
    FROM student_answers sa
    JOIN questions q ON sa.question_id = q.id
    WHERE sa.student_id = ? AND sa.is_correct = 0
    ORDER BY sa.id DESC 
    LIMIT 10
";
$stmt_wrong = $conn->prepare($sql_wrong);
$stmt_wrong->bind_param("i", $student_id);
$stmt_wrong->execute();
$result_wrong = $stmt_wrong->get_result();

// 2. For each wrong question, get a material recommendation
while ($row = $result_wrong->fetch_assoc()) {
    $rec = recommendMaterialForQuestion($conn, $row['question_id'], $row['strand_id']);

    // 3. If a good match was found and we haven't already recommended it, add it.
    if ($rec !== null && !in_array($rec['id'], $recommended_ids)) {
        $recommendations[] = $rec;
        $recommended_ids[] = $rec['id'];
    }
}
$stmt_wrong->close();

// 4. Send the final list back as JSON
echo json_encode(['success' => true, 'data' => $recommendations]);
$conn->close();
