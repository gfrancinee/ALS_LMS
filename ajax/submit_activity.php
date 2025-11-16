<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// --- 1. Basic Validation & Security ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in as a student.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$student_id = (int)($_POST['student_id'] ?? 0);
$assessment_id = (int)($_POST['assessment_id'] ?? 0);
$submission_text = trim($_POST['submission_text'] ?? '');
$submission_file_path = null;

// --- START: UPDATED VARIABLES ---
$action = $_POST['action'] ?? 'add'; // 'add' or 'edit'
$submission_id = (int)($_POST['submission_id'] ?? 0);
$remove_file = isset($_POST['remove_file']) && $_POST['remove_file'] == '1';
// --- END: UPDATED VARIABLES ---


// Double-check session ID matches form ID
if ($student_id !== (int)$_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'Session mismatch. Please refresh and try again.']);
    exit;
}

if (empty($assessment_id) || empty($student_id)) {
    echo json_encode(['success' => false, 'error' => 'Missing required data.']);
    exit;
}

// Check if at least one submission type is present
if (empty($submission_text) && (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] == UPLOAD_ERR_NO_FILE)) {
    // If they are editing, they might just be removing a file
    if ($action !== 'edit' || !$remove_file) {
        echo json_encode(['success' => false, 'error' => 'Please upload a file or write a submission.']);
        exit;
    }
}

// --- 2. Check if already submitted ---
// (This block is now removed to allow editing)
// $stmt_check = $conn->prepare("SELECT id FROM activity_submissions WHERE assessment_id = ? AND student_id = ?");
// ... (block removed) ...


// --- 3. Handle File Upload (if one exists) ---
$new_file_uploaded = false;
if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == UPLOAD_ERR_OK) {

    // Define a safe upload directory
    // IMPORTANT: Make sure this 'submissions' directory exists and is writable!
    $upload_dir = '../uploads/submissions/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_tmp_name = $_FILES['submission_file']['tmp_name'];
    $file_name = basename($_FILES['submission_file']['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Create a unique, safe filename
    // Format: sub_[assessmentID]_[studentID]_[timestamp].ext
    $safe_filename = 'sub_' . $assessment_id . '_' . $student_id . '_' . time() . '.' . $file_ext;
    $full_upload_path = $upload_dir . $safe_filename; // Full path for move_uploaded_file

    if (move_uploaded_file($file_tmp_name, $full_upload_path)) {
        // File moved successfully
        // We will store the *relative path* in the DB, not the full server path
        $submission_file_path = 'uploads/submissions/' . $safe_filename;
        $new_file_uploaded = true;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to upload file. Check directory permissions.']);
        exit;
    }
}

// --- 4. Get Existing Submission Data (if editing) ---
$existing_submission = null;
if ($action === 'edit' && !empty($submission_id)) {
    $stmt_check = $conn->prepare("SELECT submission_file FROM activity_submissions WHERE id = ? AND student_id = ?");
    $stmt_check->bind_param("ii", $submission_id, $student_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows == 0) {
        echo json_encode(['success' => false, 'error' => 'Submission not found or you do not own it.']);
        exit;
    }
    $existing_submission = $result_check->fetch_assoc();
    $stmt_check->close();
}


// --- 5. Database Operation (INSERT or UPDATE) ---

if ($action === 'edit') {
    // --- UPDATE existing submission ---

    // Determine the final file path
    $final_file_path = $existing_submission['submission_file']; // Start with the old file

    if ($new_file_uploaded) {
        // A new file was uploaded, delete the old one
        if (!empty($existing_submission['submission_file']) && file_exists('../' . $existing_submission['submission_file'])) {
            unlink('../' . $existing_submission['submission_file']);
        }
        $final_file_path = $submission_file_path; // Use the new file path
    } elseif ($remove_file) {
        // No new file, but "Remove" was checked
        if (!empty($existing_submission['submission_file']) && file_exists('../' . $existing_submission['submission_file'])) {
            unlink('../' . $existing_submission['submission_file']);
        }
        $final_file_path = null; // Set to null
    }
    // If no new file and "Remove" not checked, $final_file_path remains the old file path

    $stmt = $conn->prepare(
        "UPDATE activity_submissions 
         SET submission_text = ?, submission_file = ?, submitted_at = NOW(), status = 'submitted' 
         WHERE id = ? AND student_id = ?"
    );
    $stmt->bind_param("ssii", $submission_text, $final_file_path, $submission_id, $student_id);
} else {
    // --- INSERT new submission ---

    // Get Total Points for the assessment (if available)
    $stmt_points = $conn->prepare("SELECT total_points FROM assessments WHERE id = ?");
    $stmt_points->bind_param("i", $assessment_id);
    $stmt_points->execute();
    $result_points = $stmt_points->get_result();
    $assessment_data = $result_points->fetch_assoc();
    $total_points = $assessment_data['total_points'] ?? 0;
    $stmt_points->close();

    $stmt = $conn->prepare(
        "INSERT INTO activity_submissions (assessment_id, student_id, submission_text, submission_file, total_points, status) 
         VALUES (?, ?, ?, ?, ?, 'submitted')"
    );
    $stmt->bind_param("iissi", $assessment_id, $student_id, $submission_text, $submission_file_path, $total_points);
}

// --- 6. Execute and Respond ---
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
