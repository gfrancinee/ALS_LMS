<?php
// --- ADD THESE LINES TO SEE ERRORS ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
// -------------------------------------

session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php'; // <-- This MUST be here
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

// --- THIS IS THE FIX ---
// The SQL query MUST select 'strand_title'
$strand_check = $conn->prepare("SELECT grade_level, strand_title FROM learning_strands WHERE id = ?");
$strand_check->bind_param("i", $strand_id);
$strand_check->execute();
$strand_result = $strand_check->get_result();

if ($strand_result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Learning strand not found.']);
    exit;
}

$strand = $strand_result->fetch_assoc();
$required_grade_level_raw = $strand['grade_level'];
$strand_title = $strand['strand_title']; // <-- This now correctly gets 'strand_title'
$strand_check->close();
// --- END OF FIX ---


// --- Map the strand grade level to the user grade level ---
// This logic now handles 'Grade 11' and '11'
$user_grade_level_to_check = '';
if ($required_grade_level_raw === 'Grade 11' || $required_grade_level_raw === '11') {
    $user_grade_level_to_check = 'grade_11';
} elseif ($required_grade_level_raw === 'Grade 12') {
    $user_grade_level_to_check = 'grade_12';
} else {
    echo json_encode(['status' => 'error', 'message' => "Invalid grade level in learning strand: '{$required_grade_level_raw}'."]);
    exit;
}

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

// Prepare notification details
$notification_type = 'add_participant';
$notification_message = "You have been added to the learning strand: '" . htmlspecialchars($strand_title) . "'.";
$notification_link = "student/strand.php?id=" . $strand_id;

foreach ($student_ids as $student_id) {
    $verify_stmt->bind_param("is", $student_id, $user_grade_level_to_check);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        $skipped_count++;
        continue;
    }

    $stmt->bind_param("ii", $strand_id, $student_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success_count++;
        create_notification($conn, $student_id, $notification_type, $strand_id, $notification_message, $notification_link);
    } else {
        // This student was already in the strand. Do nothing.
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
