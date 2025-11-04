<?php
// /ajax/upload_material.php (Updated with PDF/Text Extraction)
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// --- STEP 1: LOAD THE COMPOSER AUTOLOADER ---
// This loads the new smalot/pdfparser library
// The path '__DIR__ . /../' assumes this script is in /ajax/ and vendor is in the root
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

// --- Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}
$teacher_id = $_SESSION['user_id'];

// --- Get Form Data ---
$strand_id = filter_input(INPUT_POST, 'strand_id', FILTER_VALIDATE_INT);
$category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
$label = trim($_POST['label'] ?? '');
$type = $_POST['material_type'] ?? '';

// --- Validation ---
if (empty($strand_id) || empty($category_id) || empty($label) || empty($type)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields: Category, Label, or Type.']);
    exit;
}

// --- Initialize variables ---
$file_path = null;
$link_url = null;
$content_text = null; // This is the new variable for extracted text

// --- Handle File or Link ---
if ($type === 'link') {
    $link_url = filter_input(INPUT_POST, 'link_url', FILTER_VALIDATE_URL);
    if (!$link_url) {
        echo json_encode(['success' => false, 'error' => 'The provided URL is not valid.']);
        exit;
    }
    // For links, we can try to fetch the page title as the content
    // This is advanced, so for now we'll leave content_text as null

} else { // Handles 'file', 'image', 'video', 'audio'
    if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['material_file'];
        $upload_dir = '../uploads/materials/'; // Full server path for moving
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $original_filename = basename($file['name']);
        $safe_filename = preg_replace("/[^a-zA-Z0-9\.\-\_]/", "", $original_filename);
        $filename = uniqid() . '-' . $safe_filename;
        $destination = $upload_dir . $filename; // Full server path to the new file

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // File moved successfully, set the relative path for DB
            $file_path = 'uploads/materials/' . $filename;

            // --- STEP 2: EXTRACT TEXT FROM THE UPLOADED FILE ---
            $file_extension = strtolower(pathinfo($destination, PATHINFO_EXTENSION));

            if ($file_extension == 'pdf') {
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($destination); // Parse the file from its new location
                    $extracted_text = $pdf->getText();
                    $content_text = preg_replace('/\s+/', ' ', $extracted_text); // Clean up
                } catch (Exception $e) {
                    error_log("Failed to parse PDF ($destination): " . $e->getMessage());
                    // Don't fail the upload, just proceed without extracted text
                }
            } else if ($file_extension == 'txt') {
                try {
                    $content_text = file_get_contents($destination);
                } catch (Exception $e) {
                    error_log("Failed to read TXT ($destination): " . $e->getMessage());
                }
            }
            // You could add parsers for .docx or .pptx here later
            // --- END TEXT EXTRACTION ---

        } else {
            echo json_encode(['success' => false, 'error' => 'Server error: Failed to move uploaded file.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No file was uploaded or an upload error occurred.']);
        exit;
    }
}

// --- STEP 3: Insert into Database (with content_text) ---
$stmt = $conn->prepare(
    // Added the content_text column
    "INSERT INTO learning_materials (teacher_id, strand_id, category_id, label, type, file_path, link_url, content_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
// Added 's' to the bind string for content_text
$stmt->bind_param("iiisssss", $teacher_id, $strand_id, $category_id, $label, $type, $file_path, $link_url, $content_text);

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;

    // 1. Get all students in that strand
    $stmt_students = $conn->prepare("SELECT user_id FROM strand_participants WHERE strand_id = ?");
    $stmt_students->bind_param("i", $strand_id);
    $stmt_students->execute();
    $result_students = $stmt_students->get_result();
    $student_ids = []; // Store just the IDs
    while ($row = $result_students->fetch_assoc()) {
        $student_ids[] = $row['user_id'];
    }
    $stmt_students->close();

    // 2. Loop and create/update notifications for each student
    $notification_type = 'new_material';
    $notification_link = "strand.php?id=" . $strand_id; // Links to the strand page

    $stmt_notify = $conn->prepare("
        INSERT INTO notifications (user_id, type, related_id, count, link, is_read)
        VALUES (?, ?, ?, 1, ?, 0)
        ON DUPLICATE KEY UPDATE
            count = count + 1,
            is_read = 0,
            updated_at = NOW()
    ");

    foreach ($student_ids as $student_id) {
        // Use $strand_id as the related_id for grouping
        $stmt_notify->bind_param("isss", $student_id, $notification_type, $strand_id, $notification_link);
        $stmt_notify->execute();
    }
    $stmt_notify->close();

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
