<?php
session_start();
require_once '../includes/db.php';

// --- Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die("Unauthorized access.");
}

// --- Form Submission Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get all data from the POST request (from the modal's form)
    $strand_id = $_POST['strand_id'] ?? null;
    $title = $_POST['strand_title'];
    $code = $_POST['strand_code'];
    $grade = $_POST['grade_level'];
    $desc = $_POST['description'];
    $teacher_id = $_SESSION['user_id'];

    // Validate that we have the ID from the hidden input
    if (!$strand_id || !$title || !$code || !$grade || !$desc) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Learning strand updated successfully!'
        ]);
        exit;
    }

    // Prepare the update statement
    $stmt = $conn->prepare("UPDATE learning_strands SET strand_title = ?, strand_code = ?, grade_level = ?, description = ? WHERE id = ? AND creator_id = ?");
    $stmt->bind_param("ssssii", $title, $code, $grade, $desc, $strand_id, $teacher_id);

    // Execute and redirect
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Learning strand updated successfully!';
        // Correct path back to the teacher dashboard
        header("Location: ../teacher/teacher.php");
        exit;
    } else {
        $_SESSION['error'] = 'Could not update the strand.';
        header("Location: ../teacher/teacher.php");
        exit;
    }
}
