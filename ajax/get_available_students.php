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

// This query finds all users with the 'student' role
// who are NOT IN the list of students already in this specific strand.
$stmt = $conn->prepare("
    SELECT id, fname, lname 
    FROM users 
    WHERE role = 'student' AND id NOT IN (
        SELECT student_id FROM strand_participants WHERE strand_id = ?
    )
    ORDER BY lname, fname
");
$stmt->bind_param("i", $strand_id);
$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($students);
