<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../../includes/db.php';
header('Content-Type:application/json'); // Ensure JSON response

$strand_id   = $_POST['strand_id']       ?? null;
$teacher_id  = $_SESSION['user_id']      ?? null;
$label       = $_POST['materialLabel']   ?? '';
$type        = $_POST['materialType']    ?? '';
$file_path   = null;
$link_url    = null;

// Basic validation
if (!$strand_id || !$teacher_id || !$label || !$type) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields."
    ]);
    exit;
}

// Handle file or link input
if ($type === 'link') {
    $link_url = trim($_POST['materialLink'] ?? '');
    if (!$link_url) {
        echo json_encode([
            "status" => "error",
            "message" => "Link URL is required for link type."
        ]);
        exit;
    }
} else {
    if (!isset($_FILES['materialFile'])) {
        echo json_encode([
            "status" => "error",
            "message" => "No file uploaded."
        ]);
        exit;
    }

    $uploadError = $_FILES['materialFile']['error'];
    if ($uploadError !== UPLOAD_ERR_OK) {
        echo json_encode([
            "status" => "error",
            "message" => "Upload error code: $uploadError"
        ]);
        exit;
    }

    // Ensure upload folder exists
    $uploadDir = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $originalName = basename($_FILES['materialFile']['name']);
    $filename     = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
    $tmpPath      = $_FILES['materialFile']['tmp_name'];
    $destination  = $uploadDir . $filename;

    if (move_uploaded_file($tmpPath, $destination)) {
        // Store relative path for web access
        $file_path = 'uploads/' . $filename;
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to move uploaded file."
        ]);
        exit;
    }
}

// Insert into database
$stmt = $conn->prepare("
    INSERT INTO learning_materials
      (strand_id, teacher_id, label, type, file_path, link_url)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "iissss",
    $strand_id,
    $teacher_id,
    $label,
    $type,
    $file_path,
    $link_url
);

if ($stmt->execute()) {
    echo json_encode([
        "status"  => "success",
        "message" => "Material uploaded successfully!"
    ]);
} else {
    echo json_encode([
        "status"  => "error",
        "message" => "Database error: " . $stmt->error
    ]);
}
