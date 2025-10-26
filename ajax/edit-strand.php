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

    // Added: Map form values to database ENUM values
    // Form dropdown sends: 'grade_11', 'grade_12'
    // Database expects: 'Grade 11', 'Grade 12'
    if ($grade === 'grade_11') {
        $grade = 'Grade 11';
    } elseif ($grade === 'grade_12') {
        $grade = 'Grade 12';
    }
    // If already 'Grade 11' or 'Grade 12', it stays as is

    // Added: Clean the description from TinyMCE
    // TinyMCE sends <p><br></p> or <p></p> when empty
    $desc_clean = trim(strip_tags($desc ?? ''));

    // Validate that we have the necessary data
    if (!$strand_id || !$title || !$code || !$grade || empty($desc_clean)) {
        // Added: Debug information to help identify which field is missing
        $missing = [];
        if (!$strand_id) $missing[] = 'strand_id';
        if (!$title) $missing[] = 'title';
        if (!$code) $missing[] = 'code';
        if (!$grade) $missing[] = 'grade';
        if (empty($desc_clean)) $missing[] = 'description (cannot be empty)';

        echo json_encode([
            'status' => 'error',
            'message' => 'All fields are required. Description cannot be empty.',
            'missing_fields' => $missing
        ]);
        exit;
    }

    // Prepare the update statement
    $stmt = $conn->prepare("UPDATE learning_strands SET strand_title = ?, strand_code = ?, grade_level = ?, description = ? WHERE id = ? AND creator_id = ?");
    // Assuming 'description' (s) needs to be longer than strand_title/code/level (s)
    $stmt->bind_param("ssssii", $title, $code, $grade, $desc, $strand_id, $teacher_id);

    // Execute the statement
    if ($stmt->execute()) {
        // Check if any rows were actually updated
        if ($stmt->affected_rows > 0) {
            $_SESSION['success_message'] = "Learning strand \"$title\" updated successfully!";
            echo json_encode(['status' => 'success', 'message' => 'Update successful. Reloading page.']);
        } else {
            // No rows updated - either strand doesn't exist or you're not the creator
            echo json_encode(['status' => 'error', 'message' => 'No changes were made. You may not have permission to edit this strand.']);
        }
        exit;
    } else {
        $_SESSION['error_message'] = 'Database error: Could not update the strand.';
        echo json_encode(['status' => 'error', 'message' => 'Could not update the strand. Database error: ' . $stmt->error]);
        exit;
    }
}
// If not a POST request
else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}
