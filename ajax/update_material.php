<?php
// FILE: ajax/update_material.php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// --- FIX: Allow both 'teacher' and 'admin' ---
$allowed_roles = ['teacher', 'admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check for the fields from your new modal
if (!isset($_POST['id']) || !isset($_POST['label']) || !isset($_POST['type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields (id, label, type).']);
    exit;
}

$material_id = $_POST['id'];
$label = trim($_POST['label']);
$type = $_POST['type'];
$link_url = $_POST['link_url'] ?? null;
$file_path = null;

$conn->begin_transaction();

try {
    // 1. Check if a new file is being uploaded
    // (This field is named 'file_path' in your new modal HTML)
    if (isset($_FILES['file_path']) && $_FILES['file_path']['error'] == 0) {

        $target_dir = "../uploads/materials/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        // Create a unique, safe filename
        $file_extension = strtolower(pathinfo($_FILES["file_path"]["name"], PATHINFO_EXTENSION));
        $safe_filename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($_FILES["file_path"]["name"]));
        $target_file = $target_dir . $safe_filename;

        if (move_uploaded_file($_FILES["file_path"]["tmp_name"], $target_file)) {
            // The path to store in the DB
            $file_path = "uploads/materials/" . $safe_filename;
        } else {
            throw new Exception('Failed to upload new file.');
        }

        // --- Update query to include new file_path and clear any old link ---
        $stmt = $conn->prepare("UPDATE learning_materials SET label = ?, type = ?, file_path = ?, link_url = NULL WHERE id = ?");
        $stmt->bind_param("sssi", $label, $type, $file_path, $material_id);
    } else if ($type == 'link') {
        // --- Update query for 'link' type and clear any old file ---
        $stmt = $conn->prepare("UPDATE learning_materials SET label = ?, type = ?, file_path = NULL, link_url = ? WHERE id = ?");
        $stmt->bind_param("sssi", $label, $type, $link_url, $material_id);
    } else {
        // --- Update query without changing the file/link (e.g., just a title change) ---
        // We set link_url to NULL just in case they changed from 'link' to 'file' without uploading
        $stmt = $conn->prepare("UPDATE learning_materials SET label = ?, type = ?, link_url = NULL WHERE id = ?");
        $stmt->bind_param("ssi", $label, $type, $material_id);
    }

    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        // This isn't an error, just means no data was changed
        // We can treat it as success
    }

    $stmt->close();
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Material updated successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
