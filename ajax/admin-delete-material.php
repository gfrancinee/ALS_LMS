<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An error occurred.'];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $response['message'] = 'Unauthorized.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);

    if ($id > 0) {
        // First, get the file path to delete the file
        $stmt = $conn->prepare("SELECT file_path FROM learning_materials WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $material = $result->fetch_assoc();
        $stmt->close();

        // Now, delete the record from the database
        $stmt_del = $conn->prepare("DELETE FROM learning_materials WHERE id = ?");
        $stmt_del->bind_param("i", $id);

        if ($stmt_del->execute()) {
            if ($stmt_del->affected_rows > 0) {
                // If DB delete was successful, delete the file
                if ($material && !empty($material['file_path'])) {
                    $file_on_server = '../../' . $material['file_path'];
                    if (file_exists($file_on_server)) {
                        unlink($file_on_server);
                    }
                }
                $response['success'] = true;
                $response['message'] = 'Material deleted successfully.';
            } else {
                $response['message'] = 'Material not found or already deleted.';
            }
        } else {
            $response['message'] = 'Database error: ' . $stmt_del->error;
        }
        $stmt_del->close();
    } else {
        $response['message'] = 'Invalid Material ID.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
exit;
