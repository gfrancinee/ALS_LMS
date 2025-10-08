<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security Check: Only for logged-in teachers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get data from the form
$title = $_POST['title'] ?? '';
$type = $_POST['type'] ?? 'quiz';
$category_id = $_POST['category_id'] ?: null; // Use null if empty
$description = $_POST['description'] ?? '';
$duration_minutes = $_POST['duration_minutes'] ?? 60;
$max_attempts = $_POST['max_attempts'] ?? 1;

$teacher_id = $_SESSION['user_id'];
$strand_id = $_POST['strand_id'] ?? 0; // We'll get this from JS

if (empty($title) || empty($strand_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Title and Strand ID are required.']);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO assessments (title, type, category_id, description, duration_minutes, max_attempts, teacher_id, strand_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param(
    "ssisiiii",
    $title,
    $type,
    $category_id,
    $description,
    $duration_minutes,
    $max_attempts,
    $teacher_id,
    $strand_id
);

if ($stmt->execute()) {
    // Get the ID of the assessment we just created
    $new_id = $conn->insert_id;

    // Send back the new assessment's details to the JavaScript
    echo json_encode([
        'success' => true,
        'newAssessment' => [
            'id' => $new_id,
            'title' => $title,
            'type' => $type
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
