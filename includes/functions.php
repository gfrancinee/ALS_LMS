<?php
// Function to create a notification
function create_notification($conn, $user_id, $message, $link)
{
    // We use a prepared statement to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");

    // Check if the statement was prepared successfully
    if ($stmt === false) {
        // Handle error, e.g., log it or show a generic message
        // For debugging, you can use: error_log("Prepare failed: " . $conn->error);
        return false;
    }

    // Bind the parameters
    $stmt->bind_param("iss", $user_id, $message, $link);

    // Execute the statement and return true on success, false on failure
    $success = $stmt->execute();

    // For debugging, you can check for execution errors
    // if (!$success) {
    //     error_log("Execute failed: " . $stmt->error);
    // }

    $stmt->close();

    return $success;
}
