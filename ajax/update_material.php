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
// --- REMOVED $description variable ---

if (empty($material_id) || empty($label)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

// --- UPDATED SQL QUERY (removed description) ---
$stmt = $conn->prepare("UPDATE learning_materials SET label = ? WHERE id = ? AND teacher_id = ?");

// --- UPDATED BIND_PARAM (removed "s" for description) ---
$stmt->bind_param("sii", $label, $material_id, $_SESSION['user_id']);

if ($stmt->execute()) {
    // Check if any row was actually updated (optional but good)
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        // This can happen if the ID was wrong or the label was unchanged
        echo json_encode(['success' => true, 'message' => 'No changes detected.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
$stmt->close();
$conn->close();
