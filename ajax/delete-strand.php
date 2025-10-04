<?php
require_once '../includes/db.php';

// Only allow teachers
if ($_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['strand_id'])) {
    $strand_id = $_POST['strand_id'];
    $teacher_id = $_SESSION['user_id'];

    // Security check: Make sure the teacher owns this strand before deleting
    $stmt = $conn->prepare("DELETE FROM learning_strands WHERE id = ? AND creator_id = ?");
    $stmt->bind_param("ii", $strand_id, $teacher_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Learning strand deleted successfully.';
    } else {
        // Handle error, maybe set an error message in the session
    }
    header("Location: ../teacher.php");
    exit;
}
