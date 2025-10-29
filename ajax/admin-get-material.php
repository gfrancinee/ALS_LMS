<?php
session_start();
// Make sure this path is correct: '../includes/'
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json'); // This sends JSON, not HTML
$response = ['success' => false, 'message' => 'An error occurred.'];

// Only allow Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $response['message'] = 'Unauthorized.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

// This file gets ONE material by its ID
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);

    $stmt = $conn->prepare("SELECT * FROM learning_materials WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response['success'] = true;
            $response['data'] = $result->fetch_assoc(); // Send the data
        } else {
            $response['message'] = 'Material not found.';
        }
    } else {
        $response['message'] = 'Database error: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $response['message'] = 'Invalid Material ID.';
}

$conn->close();
echo json_encode($response); // Echo the final JSON response
exit;
