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

// Get the ID from the FormData POST request
$id = $_POST['assessment_id'] ?? 0;
$teacher_id = $_SESSION['user_id'];

if (empty($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Assessment ID is required.']);
    exit;
}

// Prepare the DELETE statement, ensuring the teacher owns the assessment
$stmt = $conn->prepare("DELETE FROM assessments WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $id, $teacher_id);

if ($stmt->execute()) {
    // Check if a row was actually deleted
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        // This means the ID wasn't found or didn't belong to the teacher
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Assessment not found or you do not have permission.']);
    }
} else {
    // This would be a database server error
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
