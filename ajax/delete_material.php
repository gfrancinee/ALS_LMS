<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security checks
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['material_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$material_id = $_POST['material_id'];
$teacher_id = $_SESSION['user_id'];

// Prepare a secure delete statement
// This query ensures a teacher can only delete materials from strands they own
$stmt = $conn->prepare("
    DELETE lm FROM learning_materials lm
    JOIN learning_strands ls ON lm.strand_id = ls.id
    WHERE lm.id = ? AND ls.creator_id = ?
");

$stmt->bind_param("ii", $material_id, $teacher_id);

if ($stmt->execute()) {
    // Check if a row was actually deleted
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        // No rows deleted, likely because the teacher doesn't own this material
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this material or it does not exist.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}

$stmt->close();
$conn->close();
