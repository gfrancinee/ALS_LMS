<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Ensure a teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Get the JSON data sent from the JavaScript
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

$strand_id = $data['strand_id'] ?? null;
$student_ids = $data['student_ids'] ?? [];

// Validation
if (!$strand_id || !is_array($student_ids) || empty($student_ids)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data provided.']);
    exit;
}

// Prepare the SQL statement ONCE outside the loop for efficiency
// INSERT IGNORE will prevent errors if you try to add a student who is already enrolled
$stmt = $conn->prepare("
    INSERT IGNORE INTO strand_participants (strand_id, student_id) 
    VALUES (?, ?)
");

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement.']);
    exit;
}

$success_count = 0;
// Loop through each student ID and insert it into the database
foreach ($student_ids as $student_id) {
    $stmt->bind_param("ii", $strand_id, $student_id);
    if ($stmt->execute()) {
        $success_count++;
    }
}

echo json_encode([
    'status' => 'success',
    'message' => "Successfully added $success_count participant(s)."
]);

$stmt->close();
$conn->close();
