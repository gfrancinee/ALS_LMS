<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security: Ensure a teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Get the ID from the JSON request
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$teacher_id = $_SESSION['user_id'];

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Assessment ID is required.']);
    exit;
}

// Prepare the DELETE statement
// We also check teacher_id to make sure a teacher can only delete their own assessments
$stmt = $conn->prepare("DELETE FROM assessments WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $id, $teacher_id);

if ($stmt->execute()) {
    // Check if a row was actually deleted
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Assessment deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Assessment not found or you do not have permission to delete it.']);
    }
} else {
    // This would be a database server error
    echo json_encode(['success' => false, 'message' => 'Failed to delete assessment.']);
}

$stmt->close();
$conn->close();
