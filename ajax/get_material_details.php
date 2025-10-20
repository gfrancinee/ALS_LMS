<?php
// /ajax/get_material_details.php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$material_id = $_GET['id'] ?? 0;
if (!$material_id) {
    echo json_encode(['success' => false, 'error' => 'Material ID not provided.']);
    exit;
}

// --- FIX: Select ONLY the 'label' ---
// This is much faster as it doesn't fetch the large 'content_text'
$stmt = $conn->prepare("SELECT label FROM learning_materials WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $material_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$material = $result->fetch_assoc();
$stmt->close();

if ($material) {
    // The $material array now only contains ['label' => '...']
    echo json_encode(['success' => true, 'data' => $material]);
} else {
    echo json_encode(['success' => false, 'error' => 'Material not found or you do not have permission.']);
}
