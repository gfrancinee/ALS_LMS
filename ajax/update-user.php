<?php
require_once '../../includes/db.php';

// Check if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $id     = intval($_POST['id'] ?? 0);
    $fname  = trim($_POST['fname'] ?? '');
    $lname  = trim($_POST['lname'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $role   = trim($_POST['role'] ?? '');

    // Basic validation
    if ($id <= 0 || empty($fname) || empty($lname) || empty($email) || empty($role)) {
        die('Invalid input. Please fill out all fields.');
    }

    // Prepare SQL statement
    $stmt = $conn->prepare("UPDATE users SET fname = ?, lname = ?, email = ?, role = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $fname, $lname, $email, $role, $id);

    if ($stmt->execute()) {
        // Redirect back to user management page
        header("Location: admin-users.php?updated=1");
        exit;
    } else {
        echo "Error updating user: " . $conn->error;
    }

    $stmt->close();
} else {
    echo "Invalid request method.";
}
