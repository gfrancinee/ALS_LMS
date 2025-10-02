<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Only allow teachers to create strands
if ($_SESSION['role'] !== 'teacher') {
    header("Location: ../../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['strand_title'];
    $code = $_POST['strand_code'];
    $grade = $_POST['grade_level'];
    $desc = $_POST['description'];
    $creator_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO learning_strands (strand_title, strand_code, grade_level, description, creator_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $title, $code, $grade, $desc, $creator_id);

    if ($stmt->execute()) {
        // Get the ID of the new strand we just created
        $new_strand_id = $conn->insert_id;

        // The role 'admin' here refers to the admin of this specific strand
        $role_in_strand = 'admin';
        $stmt_participant = $conn->prepare("INSERT INTO strand_participants (strand_id, student_id, role) VALUES (?, ?, ?)");
        $stmt_participant->bind_param("iis", $new_strand_id, $creator_id, $role_in_strand);
        $stmt_participant->execute();
        $stmt_participant->close();
        // Set a success message to be displayed on the dashboard
        $_SESSION['success'] = 'Learning strand "' . htmlspecialchars($title) . '" created successfully!';
    } else {
        // Optionally, handle errors
        // $_SESSION['error'] = 'Failed to create learning strand.';
    }

    $stmt->close();
    $conn->close();

    // --- THIS IS THE FIX ---
    // Redirect back UP ONE LEVEL to the main teacher.php page
    header("Location: ../teacher.php");
    exit;
}
