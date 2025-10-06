<?php
// CRITICAL FIX 1: Start the session
session_start();
require_once '../includes/db.php';

// Only allow teachers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    // Return JSON error status for unauthorized access
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['strand_id'])) {
    $strand_id = $_POST['strand_id'];
    $teacher_id = $_SESSION['user_id'];

    // Security check: Make sure the teacher owns this strand before deleting
    $stmt = $conn->prepare("DELETE FROM learning_strands WHERE id = ? AND creator_id = ?");
    $stmt->bind_param("ii", $strand_id, $teacher_id);

    if ($stmt->execute()) {
        // CRITICAL FIX 2: Use consistent session key and REMOVE THE REDIRECT
        $_SESSION['success_message'] = 'Learning strand deleted successfully!';

        // CRITICAL FIX 3: Return JSON success status
        echo json_encode(['status' => 'success', 'message' => 'Deletion successful. Reloading page.']);
        exit;
    } else {
        // Handle error, using the consistent error session key
        $_SESSION['error_message'] = 'Database error: Could not delete the learning strand.';

        // CRITICAL FIX 3: Return JSON error status
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete the learning strand.']);
        exit;
    }
} else {
    // If strand_id is missing or request method is wrong
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data.']);
    exit;
}
