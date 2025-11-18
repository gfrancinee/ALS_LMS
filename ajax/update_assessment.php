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
$teacher_id = $_SESSION['user_id'];
$category_id = ($_POST['category_id'] === '') ? null : ($_POST['category_id'] ?? null);

// --- UPDATED: This logic is now type-aware ---
// This fixes the bug where projects would re-save duration/attempts
$duration_minutes = 0;
$max_attempts = 0;
$total_points = 0;

if ($type === 'quiz' || $type === 'exam') {
    $duration_minutes = $_POST['duration_minutes'] ?? 60;
    $max_attempts = $_POST['max_attempts'] ?? 1;
    // total_points remains 0 for quizzes
} else {
    // This is for 'activity', 'assignment', or 'project'
    $total_points = $_POST['total_points'] ?? 0; // Read the total_points value
    // duration_minutes and max_attempts remain 0
}
// --- END: UPDATED LOGIC ---


if (empty($title) || empty($assessment_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Title and Assessment ID are required.']);
    exit;
}

// --- UPDATED: Added 'total_points = ?' to the SQL query ---
$stmt = $conn->prepare(
    "UPDATE assessments SET 
     title = ?, 
     type = ?, 
     category_id = ?, 
     description = ?, 
     duration_minutes = ?, 
     max_attempts = ?, 
     total_points = ? 
     WHERE id = ? AND teacher_id = ?"
);

// --- UPDATED: Added 'i' and $total_points to the bind_param ---
$stmt->bind_param(
    "ssisiiiii",
    $title,
    $type,
    $category_id,
    $description,
    $duration_minutes,
    $max_attempts,
    $total_points, // The new variable
    $assessment_id,
    $teacher_id
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
