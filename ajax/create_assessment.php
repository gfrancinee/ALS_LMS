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
$teacher_id = $_SESSION['user_id'];
$strand_id = $_POST['strand_id'] ?? 0;
$category_id = empty($_POST['category_id']) ? null : $_POST['category_id'];

// --- START: UPDATED LOGIC ---
// This logic correctly reads total_points for non-quiz types

$duration_minutes = 0; // Default to 0 for all types
$max_attempts = 0; // Default to 0 for all types
$total_points = 0; // Default to 0 for all types

// *ONLY* if the type is a quiz or exam, read the duration/attempts
if ($type === 'quiz' || $type === 'exam') {
    $duration_minutes = $_POST['duration_minutes'] ?? 60;
    $max_attempts = $_POST['max_attempts'] ?? 1;
    // total_points remains 0 for quizzes, as it's calculated from questions
} else {
    // This is for 'activity', 'assignment', or 'project'
    // duration_minutes and max_attempts remain 0
    $total_points = $_POST['total_points'] ?? 20; // Read the total_points value from the form
}
// --- END: UPDATED LOGIC ---


// Validation
if (empty($title) || empty($strand_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Title and Strand ID are required.']);
    exit;
}

// --- UPDATED: SQL Statement (added total_points) ---
$stmt = $conn->prepare(
    "INSERT INTO assessments (title, type, category_id, description, duration_minutes, max_attempts, total_points, teacher_id, strand_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
// --- UPDATED: Bind the variables (added 'i' for total_points and $total_points) ---
$stmt->bind_param("ssisiiiii", $title, $type, $category_id, $description, $duration_minutes, $max_attempts, $total_points, $teacher_id, $strand_id);

// Execute and respond
if ($stmt->execute()) {
    $new_id = $conn->insert_id;

    // IMPORTANT: Send back ALL the data the JavaScript needs to build the new item
    $new_assessment_data = [
        'id' => $new_id,
        'title' => htmlspecialchars($title),
        'category_id' => $category_id,
        'type' => htmlspecialchars($type),
        'description' => $description, // Keep original for TinyMCE display
        'duration_minutes' => $duration_minutes,
        'max_attempts' => $max_attempts,
        'total_points' => $total_points // --- UPDATED: Send back total_points ---
    ];

    // Use 'assessment' as the key to match the JavaScript
    echo json_encode(['success' => true, 'assessment' => $new_assessment_data]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
