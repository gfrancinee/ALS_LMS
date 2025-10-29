<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Check if ID is passed via GET and is valid
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);

    // Prepare and execute delete query
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Redirect with success flag
        header("Location: admin-users.php?deleted=1");
        exit;
    } else {
        echo "Error deleting user: " . $conn->error;
    }

    $stmt->close();
} else {
    echo "Invalid user ID.";
}
