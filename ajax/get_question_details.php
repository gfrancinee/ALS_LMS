<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$question_id = $_GET['question_id'] ?? 0;
if (empty($question_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Question ID not provided.']);
    exit;
}

try {
    // Fetch the main question data
    $stmt1 = $conn->prepare("SELECT question_text, question_type FROM question_bank WHERE id = ?");
    $stmt1->bind_param("i", $question_id);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $question = $result1->fetch_assoc();
    $stmt1->close();

    if (!$question) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Question not found.']);
        exit;
    }

    // Fetch the options/answers for that question
    $stmt2 = $conn->prepare("SELECT option_text, is_correct FROM question_options WHERE question_id = ?");
    $stmt2->bind_param("i", $question_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $options = $result2->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();

    // Combine and send back the data
    echo json_encode([
        'success' => true,
        'question' => $question,
        'options' => $options
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
