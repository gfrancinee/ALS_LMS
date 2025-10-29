<?php
// UPDATED PATH: ../includes/
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An error occurred.'];

// Check if user is an ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $response['message'] = 'Unauthorized. Admin access required.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = intval($_POST['id'] ?? 0);

    if ($id > 0) {

        $stmt = $conn->prepare("DELETE FROM learning_strands WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Learning strand deleted successfully.';
            } else {
                $response['message'] = 'Strand not found or already deleted.';
            }
        } else {
            if ($conn->errno == 1451) {
                $response['message'] = 'Cannot delete this strand because it has associated materials or quizzes. Please remove them first.';
            } else {
                $response['message'] = 'Database error: ' . $stmt->error;
            }
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid strand ID provided.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
exit;
