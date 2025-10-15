<?php
// /ajax/update_material.php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get data from POST request
$material_id = $_POST['material_id'] ?? 0;
$label = trim($_POST['label'] ?? '');
$description = trim($_POST['description'] ?? '');
// Note: We don't allow changing the 'type' after creation to keep it simple.

if (empty($material_id) || empty($label)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

// Logic to update the material will go here in a future step
// For now, we'll just update the text fields
$stmt = $conn->prepare("UPDATE learning_materials SET label = ?, description = ? WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ssii", $label, $description, $material_id, $_SESSION['user_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
$stmt->close();
