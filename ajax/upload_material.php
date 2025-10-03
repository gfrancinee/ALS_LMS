<?php
session_start();
// This path is correct for a file in the root 'ajax' folder
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security check: only allow teachers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Validation: Check for all required POST variables, including teacher_id
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['strand_id']) || empty($_POST['teacher_id']) || empty($_POST['materialLabel'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request. Required fields are missing.']);
    exit;
}

$strand_id = $_POST['strand_id'];
$teacher_id = $_POST['teacher_id']; // Get the teacher ID from the form
$label = $_POST['materialLabel'];
$type = $_POST['materialType'];
$link_url = null;
$file_path = null;

// Handle link type
if ($type === 'link') {
    $link_url = $_POST['materialLink'] ?? null;
    if (empty($link_url)) {
        echo json_encode(['status' => 'error', 'message' => 'Link URL is required for link type.']);
        exit;
    }
}
// Handle file upload for all other types
else if (isset($_FILES['materialFile']) && $_FILES['materialFile']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Create a safe, unique filename
    $fileName = uniqid() . '_' . basename($_FILES['materialFile']['name']);
    $targetFilePath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['materialFile']['tmp_name'], $targetFilePath)) {
        // Store the public-facing path
        $file_path = 'uploads/' . $fileName;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file.']);
        exit;
    }
} else if ($type !== 'link') {
    echo json_encode(['status' => 'error', 'message' => 'No file was uploaded or an upload error occurred.']);
    exit;
}

// Prepare the final SQL statement to insert into the database
$stmt = $conn->prepare(
    "INSERT INTO learning_materials (strand_id, teacher_id, label, type, file_path, link_url) 
     VALUES (?, ?, ?, ?, ?, ?)"
);
// Bind all parameters, including the new integer 'i' for teacher_id
$stmt->bind_param("iissss", $strand_id, $teacher_id, $label, $type, $file_path, $link_url);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Material uploaded successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database insert failed.']);
}

$stmt->close();
$conn->close();
