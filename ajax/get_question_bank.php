<?php
// ajax/get_question_bank.php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security check: Ensure user is a logged-in teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$teacher_id = $_SESSION['user_id'];
$assessment_id = $_GET['assessment_id'] ?? 0; // Get assessment ID to exclude already added questions
$search_term = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';

// --- Build the SQL Query ---
// Base query selects questions created by the current teacher
$sql = "SELECT id, question_text, question_type FROM question_bank WHERE teacher_id = ?";
$params = [$teacher_id];
$types = "i";

// Exclude questions already in the current assessment
if (!empty($assessment_id)) {
    $sql .= " AND id NOT IN (SELECT question_id FROM assessment_questions WHERE assessment_id = ?)";
    $params[] = $assessment_id;
    $types .= "i";
}

// Add search term filter (simple LIKE search on question text)
if (!empty($search_term)) {
    $sql .= " AND question_text LIKE ?";
    $params[] = "%" . $search_term . "%";
    $types .= "s";
}

// Add question type filter
if (!empty($type_filter)) {
    $sql .= " AND question_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

$sql .= " ORDER BY id DESC LIMIT 100"; // Limit results for performance

// --- Prepare and Execute ---
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'SQL prepare failed: ' . $conn->error]);
    exit;
}

// Dynamically bind parameters
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$questions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// --- Send Response ---
echo json_encode(['success' => true, 'data' => $questions]);
