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
$assessment_id = $_POST['assessment_id'] ?? 0;
$title = $_POST['title'] ?? '';
$type = $_POST['type'] ?? 'quiz';
$description = $_POST['description'] ?? '';
$duration_minutes = $_POST['duration_minutes'] ?? 60;
$max_attempts = $_POST['max_attempts'] ?? 1;
$teacher_id = $_SESSION['user_id'];

// --- THIS IS THE UPDATED LINE ---
// This safely handles the '(No Category)' option by converting an empty string to NULL for the database.
$category_id = ($_POST['category_id'] === '') ? null : ($_POST['category_id'] ?? null);


if (empty($title) || empty($assessment_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Title and Assessment ID are required.']);
    exit;
}

// Prepare the update statement
$stmt = $conn->prepare(
    "UPDATE assessments SET title=?, type=?, category_id=?, description=?, duration_minutes=?, max_attempts=? WHERE id=? AND teacher_id=?"
);
$stmt->bind_param(
    "ssisiiii",
    $title,
    $type,
    $category_id,
    $description,
    $duration_minutes,
    $max_attempts,
    $assessment_id,
    $teacher_id // Security: Ensures teachers can only edit their own assessments
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
