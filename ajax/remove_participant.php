<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security & Validation
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get data from JSON body
$data = json_decode(file_get_contents('php://input'), true);
$participant_id = $data['participant_id'] ?? null;
$strand_id = $data['strand_id'] ?? null;
$teacher_id = $_SESSION['user_id'];

if (!$participant_id || !$strand_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

// Security check: Teacher can only remove participants from a strand they created.
$stmt = $conn->prepare("
    DELETE sp FROM strand_participants sp
    JOIN learning_strands ls ON sp.strand_id = ls.id
    WHERE sp.id = ? AND ls.id = ? AND ls.creator_id = ?
");
$stmt->bind_param("iii", $participant_id, $strand_id, $teacher_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Permission denied or participant not found.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}

$stmt->close();
$conn->close();
