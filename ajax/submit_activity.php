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
    echo json_encode(['success' => false, 'error' => 'Please upload a file or write a submission.']);
    exit;
}

// --- 2. Check if already submitted ---
// (We set this up to be editable, but for now, let's just insert a new one)
// TODO: Add logic here to UPDATE an existing submission if you want it to be editable.
// For now, we will assume one submission only.
$stmt_check = $conn->prepare("SELECT id FROM activity_submissions WHERE assessment_id = ? AND student_id = ?");
$stmt_check->bind_param("ii", $assessment_id, $student_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
if ($result_check->num_rows > 0) {
    // We are blocking a second submission for now
    echo json_encode(['success' => false, 'error' => 'You have already submitted this activity.']);
    exit;
}
$stmt_check->close();


// --- 3. Handle File Upload (if one exists) ---
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
    $submission_file_path = $upload_dir . $safe_filename;

    if (move_uploaded_file($file_tmp_name, $submission_file_path)) {
        // File moved successfully
        // We will store the *relative path* in the DB, not the full server path
        $submission_file_path = 'uploads/submissions/' . $safe_filename;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to upload file. Check directory permissions.']);
        exit;
    }
}

// --- 4. Get Total Points for the assessment (if available) ---
// We need this to store with the submission for easy grading later
$stmt_points = $conn->prepare("SELECT total_points FROM assessments WHERE id = ?");
$stmt_points->bind_param("i", $assessment_id);
$stmt_points->execute();
$result_points = $stmt_points->get_result();
$assessment_data = $result_points->fetch_assoc();
// Use total_points from assessment, or default to 0 if not set
$total_points = $assessment_data['total_points'] ?? 0;
$stmt_points->close();


// --- 5. Insert into Database ---
$stmt = $conn->prepare(
    "INSERT INTO activity_submissions (assessment_id, student_id, submission_text, submission_file, total_points, status) 
     VALUES (?, ?, ?, ?, ?, 'submitted')"
);
$stmt->bind_param("iissi", $assessment_id, $student_id, $submission_text, $submission_file_path, $total_points);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
