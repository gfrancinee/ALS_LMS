<?php
// Turn off error reporting to prevent it from breaking the JSON response
error_reporting(0);

session_start();
require_once '../includes/db.php';
// ADDED FOR NOTIFICATIONS: Include the new functions file
require_once '../includes/functions.php';

// Set the header AFTER all requires
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

// Handle link or file (This section is unchanged)
if ($type === 'link') {
    $link_url = $_POST['materialLink'] ?? null;
    if (empty($link_url) || !filter_var($link_url, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => 'error', 'message' => 'A valid Link URL is required.']);
        exit;
    }
} else if (isset($_FILES['materialFile']) && $_FILES['materialFile']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
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

    // ADDED FOR NOTIFICATIONS: This whole block creates the notifications after a successful upload
    // -----------------------------------------------------------------------------------------
    // 1. Get all students in the strand
    $student_ids = [];
    $stmt_students = $conn->prepare("SELECT student_id FROM strand_participants WHERE strand_id = ? AND role = 'student'");
    $stmt_students->bind_param("i", $strand_id);
    $stmt_students->execute();
    $result = $stmt_students->get_result();
    while ($row = $result->fetch_assoc()) {
        $student_ids[] = $row['student_id'];
    }
    $stmt_students->close();

    // 2. Get the strand title for a better message
    $strand_title = "your course"; // A default title
    $stmt_title = $conn->prepare("SELECT strand_title FROM learning_strands WHERE id = ?");
    $stmt_title->bind_param("i", $strand_id);
    $stmt_title->execute();
    if ($title_row = $stmt_title->get_result()->fetch_assoc()) {
        $strand_title = $title_row['strand_title'];
    }
    $stmt_title->close();

    // 3. Define the notification message and link
    $message = "New material has been uploaded in '" . htmlspecialchars($strand_title) . "'";
    $link = "strand/strand.php?id=" . $strand_id;

    // 4. Loop through each student and call the function from functions.php
    foreach ($student_ids as $student_id) {
        create_notification($conn, $student_id, $message, $link);
    }
    // -----------------------------------------------------------------------------------------

    echo json_encode(['status' => 'success', 'message' => 'Material uploaded successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database insert failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
// ADDED FOR NOTIFICATIONS: Ensure the script stops here
exit;
