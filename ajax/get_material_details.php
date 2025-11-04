<?php
// FILE: ajax/get_material_details.php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// --- FIX: Allow both 'teacher' and 'admin' ---
$allowed_roles = ['teacher', 'admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$material_id = $_GET['id'] ?? 0;
if (!$material_id) {
    echo json_encode(['success' => false, 'error' => 'Material ID not provided.']);
    exit;
}

// --- FIX: Select all the fields the modal needs ---
$stmt = $conn->prepare("SELECT label, type, file_path, link_url FROM learning_materials WHERE id = ?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();
$material = $result->fetch_assoc();
$stmt->close();

if ($material) {
    // Send all material data
    echo json_encode(['success' => true, 'data' => $material]);
} else {
    echo json_encode(['success' => false, 'error' => 'Material not found.']);
}
