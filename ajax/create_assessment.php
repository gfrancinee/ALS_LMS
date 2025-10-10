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

// Get data from the form
$title = trim($_POST['title'] ?? '');
$type = $_POST['type'] ?? 'quiz';
$description = $_POST['description'] ?? '';
$duration_minutes = $_POST['duration_minutes'] ?? 60;
$max_attempts = $_POST['max_attempts'] ?? 1;
$teacher_id = $_SESSION['user_id'];
$strand_id = $_POST['strand_id'] ?? 0;

// This is the updated, safer way to handle an empty category
$category_id = empty($_POST['category_id']) ? null : $_POST['category_id'];

// Validation
if (empty($title) || empty($strand_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Title and Strand ID are required.']);
    exit;
}

// SQL Statement
$stmt = $conn->prepare(
    "INSERT INTO assessments (title, type, category_id, description, duration_minutes, max_attempts, teacher_id, strand_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("ssisiiii", $title, $type, $category_id, $description, $duration_minutes, $max_attempts, $teacher_id, $strand_id);

// Execute and respond
if ($stmt->execute()) {
    $new_id = $conn->insert_id;

    // IMPORTANT: Send back ALL the data the JavaScript needs to build the new item
    $new_assessment_data = [
        'id' => $new_id,
        'title' => $title,
        'category_id' => $category_id,
        'type' => $type,
        'description' => $description,
        'duration_minutes' => $duration_minutes,
        'max_attempts' => $max_attempts
    ];
    echo json_encode(['success' => true, 'data' => $new_assessment_data]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
