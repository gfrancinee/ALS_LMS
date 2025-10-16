<?php
// /ajax/upload_material.php (Final Corrected Version - Direct Strand ID)
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

// --- Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}
$teacher_id = $_SESSION['user_id'];

// --- THIS IS THE FIX: Read strand_id DIRECTLY from POST data ---
$strand_id = filter_input(INPUT_POST, 'strand_id', FILTER_VALIDATE_INT);
$category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
$label = trim($_POST['label'] ?? '');
$type = $_POST['material_type'] ?? '';

// --- Validation for all required fields ---
if (empty($strand_id)) {
    echo json_encode(['success' => false, 'error' => 'Strand ID was not received. This is a bug.']);
    exit;
}
if (empty($category_id) || empty($label) || empty($type)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields: Category, Label, or Type.']);
    exit;
}

// The unnecessary database lookup for strand_id has been REMOVED.

$file_path = null;
$link_url = null;

// --- Handle File or Link ---
if ($type === 'link') {
    $link_url = filter_input(INPUT_POST, 'link_url', FILTER_VALIDATE_URL);
    if (!$link_url) {
        echo json_encode(['success' => false, 'error' => 'The provided URL is not valid.']);
        exit;
    }
} else { // Handles 'file', 'image', 'video', 'audio'
    if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['material_file'];
        $upload_dir = '../uploads/materials/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $original_filename = basename($file['name']);
        $safe_filename = preg_replace("/[^a-zA-Z0-9\.\-\_]/", "", $original_filename);
        $filename = uniqid() . '-' . $safe_filename;
        $destination = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $file_path = 'uploads/materials/' . $filename;
        } else {
            echo json_encode(['success' => false, 'error' => 'Server error: Failed to move uploaded file.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No file was uploaded or an upload error occurred.']);
        exit;
    }
}

// --- Insert into Database (Corrected: uses the direct strand_id) ---
$stmt = $conn->prepare(
    "INSERT INTO learning_materials (teacher_id, strand_id, category_id, label, type, file_path, link_url) VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("iiissss", $teacher_id, $strand_id, $category_id, $label, $type, $file_path, $link_url);

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;

    // --- Notification Logic ---
    $student_ids = [];
    $stmt_students = $conn->prepare("SELECT student_id FROM strand_participants WHERE strand_id = ? AND role = 'student'");
    $stmt_students->bind_param("i", $strand_id);
    $stmt_students->execute();
    $result_students = $stmt_students->get_result();
    while ($row = $result_students->fetch_assoc()) {
        $student_ids[] = $row['student_id'];
    }
    $stmt_students->close();

    $strand_title = "your course";
    $stmt_title = $conn->prepare("SELECT strand_title FROM learning_strands WHERE id = ?");
    $stmt_title->bind_param("i", $strand_id);
    $stmt_title->execute();
    if ($title_row = $stmt_title->get_result()->fetch_assoc()) {
        $strand_title = $title_row['strand_title'];
    }
    $stmt_title->close();

    $message = "New material '" . htmlspecialchars($label) . "' has been added to '" . htmlspecialchars($strand_title) . "'.";
    $link = "strand/strand.php?id=" . $strand_id;

    foreach ($student_ids as $student_id) {
        create_notification($conn, $student_id, $message, $link);
    }
    // --- End Notification Logic ---

    // Send the correct JSON response for the "no-reload" feature
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $new_id,
            'label' => $label,
            'type' => $type,
            'file_path' => $file_path,
            'link_url' => $link_url
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
exit;
