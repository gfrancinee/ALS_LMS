<?php
// /ajax/delete_material.php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$material_id = $_POST['id'] ?? 0;
if (empty($material_id)) {
    echo json_encode(['success' => false, 'error' => 'Material ID not provided.']);
    exit;
}

// Optional: Code to delete the physical file from your server
// $stmt = $conn->prepare("SELECT file_path FROM learning_materials WHERE id = ? AND teacher_id = ?");
// ... fetch file_path ...
// if (file_exists('../' . $file_path)) { unlink('../' . $file_path); }

$stmt = $conn->prepare("DELETE FROM learning_materials WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $material_id, $_SESSION['user_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
$stmt->close();
