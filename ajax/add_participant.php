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

// Get the grade level of the learning strand
$strand_check = $conn->prepare("SELECT grade_level FROM learning_strands WHERE id = ?");
$strand_check->bind_param("i", $strand_id);
$strand_check->execute();
$strand_result = $strand_check->get_result();

if ($strand_result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Learning strand not found.']);
    exit;
}

$strand = $strand_result->fetch_assoc();
$required_grade_level_raw = $strand['grade_level']; // e.g., "Grade 11"
$strand_check->close();

// --- FIX: Map the strand grade level to the user grade level ---
$user_grade_level_to_check = '';
if ($required_grade_level_raw === 'Grade 11') {
    $user_grade_level_to_check = 'grade_11';
} elseif ($required_grade_level_raw === 'Grade 12') {
    $user_grade_level_to_check = 'grade_12';
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid grade level in learning strand.']);
    exit;
}
// --- END OF FIX ---

$stmt = $conn->prepare("
    INSERT IGNORE INTO strand_participants (strand_id, student_id) 
    VALUES (?, ?)
");

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement.']);
    exit;
}

// Verify student grade levels before adding
$verify_stmt = $conn->prepare("
    SELECT id, grade_level FROM users 
    WHERE id = ? AND role = 'student' AND grade_level = ?
");

$success_count = 0;
$skipped_count = 0;

foreach ($student_ids as $student_id) {
    // --- FIX: Check against the correct mapped variable ---
    $verify_stmt->bind_param("is", $student_id, $user_grade_level_to_check);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        // Student doesn't have the required grade level, skip
        $skipped_count++;
        continue;
    }

    // Student has correct grade level, proceed to add
    $stmt->bind_param("ii", $strand_id, $student_id);
    if ($stmt->execute()) {
        $success_count++;
    }
}

$message = "Successfully added $success_count participant(s).";
if ($skipped_count > 0) {
    $message .= " Skipped $skipped_count student(s) with incorrect grade level.";
}

echo json_encode([
    'status' => 'success',
    'message' => $message
]);

$verify_stmt->close();
$stmt->close();
$conn->close();
