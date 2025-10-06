<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security check: only allow teachers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['strand_id']) || empty($_POST['teacher_id']) || empty($_POST['materialLabel'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request. Required fields are missing.']);
    exit;
}

$strand_id = $_POST['strand_id'];
$teacher_id = $_POST['teacher_id'];
$label = $_POST['materialLabel'];
$type = $_POST['materialType'];
$link_url = null;
$file_path = null;

// Handle link or file
if ($type === 'link') {
    $link_url = $_POST['materialLink'] ?? null;
    if (empty($link_url) || !filter_var($link_url, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => 'error', 'message' => 'A valid Link URL is required.']);
        exit;
    }
} else if (isset($_FILES['materialFile']) && $_FILES['materialFile']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        // Attempt to create the directory
        if (!mkdir($uploadDir, 0777, true)) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create uploads directory. Check permissions.']);
            exit;
        }
    }

    $fileName = uniqid() . '_' . basename($_FILES['materialFile']['name']);
    $targetFilePath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['materialFile']['tmp_name'], $targetFilePath)) {
        $file_path = 'uploads/' . $fileName;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file. Check folder permissions.']);
        exit;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No file was uploaded or an upload error occurred.']);
    exit;
}

// Insert into database
$stmt = $conn->prepare("INSERT INTO learning_materials (strand_id, teacher_id, label, type, file_path, link_url) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iissss", $strand_id, $teacher_id, $label, $type, $file_path, $link_url);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Material uploaded successfully.']);
} else {
    // Provide a more specific database error for debugging
    echo json_encode(['status' => 'error', 'message' => 'Database insert failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
