<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Ensure only a logged-in teacher can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$strand_id = $_GET['strand_id'] ?? null;
if (!$strand_id) {
    echo json_encode(['error' => 'Strand ID is required.']);
    exit;
}

// Added: Get the grade level of the learning strand
$strand_check = $conn->prepare("SELECT grade_level FROM learning_strands WHERE id = ?");
$strand_check->bind_param("i", $strand_id);
$strand_check->execute();
$strand_result = $strand_check->get_result();

if ($strand_result->num_rows === 0) {
    echo json_encode(['error' => 'Learning strand not found.']);
    exit;
}

$strand = $strand_result->fetch_assoc();
$required_grade_level = $strand['grade_level'];
$strand_check->close();

// Updated: This query finds all users with the 'student' role
// who match the strand's grade level AND are NOT already in this specific strand.
$stmt = $conn->prepare("
    SELECT id, fname, lname, grade_level
    FROM users 
    WHERE role = 'student' 
    AND grade_level = ?
    AND id NOT IN (
        SELECT student_id FROM strand_participants WHERE strand_id = ?
    )
    ORDER BY lname, fname
");
$stmt->bind_param("si", $required_grade_level, $strand_id);
$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($students);
