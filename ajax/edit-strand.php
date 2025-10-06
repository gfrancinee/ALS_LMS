<?php
session_start();
require_once '../includes/db.php';

// --- Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    // Return JSON error for unauthorized access
    http_response_code(401); // Set HTTP status code for unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// --- Form Submission Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get all data from the POST request (from the modal's form)
    $strand_id = $_POST['id'] ?? $_POST['strand_id'] ?? null; // Added check for 'id' and 'strand_id'
    $title = $_POST['strand_title'] ?? null;
    $code = $_POST['strand_code'] ?? null;
    $grade = $_POST['grade_level'] ?? null;
    $desc = $_POST['description'] ?? null;
    $teacher_id = $_SESSION['user_id'];

    // Validate that we have the necessary data
    if (!$strand_id || !$title || !$code || !$grade || !$desc) {
        // FIXED: This should be an 'error' status, not 'success'
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required data for update.'
        ]);
        exit;
    }

    // Prepare the update statement
    $stmt = $conn->prepare("UPDATE learning_strands SET strand_title = ?, strand_code = ?, grade_level = ?, description = ? WHERE id = ? AND creator_id = ?");
    // Assuming 'description' (s) needs to be longer than strand_title/code/level (s)
    $stmt->bind_param("ssssii", $title, $code, $grade, $desc, $strand_id, $teacher_id);

    // Execute the statement
    if ($stmt->execute()) {
        // --- FIXED: Set session message and return JSON success status ---
        $_SESSION['success_message'] = "Learning strand \"$title\" updated successfully!";

        // Return JSON status, so JavaScript can reload the page
        echo json_encode(['status' => 'success', 'message' => 'Update successful. Reloading page.']);
        exit;
    } else {
        // --- FIXED: Set session message (error) and return JSON error status ---
        // Using mysqli_error if needed for debugging, but keeping generic for production
        $_SESSION['error_message'] = 'Database error: Could not update the strand.';

        // Return JSON error status
        echo json_encode(['status' => 'error', 'message' => 'Could not update the strand.']);
        exit;
    }
}
// If not a POST request
else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}
