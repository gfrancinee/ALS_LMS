<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$strand_id = $data['strand_id'] ?? null;
$student_id = $data['student_id'] ?? null;

if (!$strand_id || !$student_id) {
    echo json_encode(['status' => 'error', 'message' => 'Required data is missing.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM strand_participants WHERE strand_id = ? AND student_id = ?");
$stmt->bind_param("ii", $strand_id, $student_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Participant removed.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to remove participant.']);
}

$stmt->close();
$conn->close();
