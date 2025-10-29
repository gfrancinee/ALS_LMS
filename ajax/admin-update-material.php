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
    $label = trim($_POST['label'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $link_url = trim($_POST['link_url'] ?? '');

    if (empty($label) || empty($type) || $id === 0) {
        $response['message'] = 'Missing required fields.';
        echo json_encode($response);
        exit;
    }

    // Get current material data to check old file path
    $stmt = $conn->prepare("SELECT file_path, type FROM learning_materials WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_material = $result->fetch_assoc();
    $current_file_path = $current_material['file_path'];
    $stmt->close();

    $file_path_sql = ""; // This will form part of the SQL query

    // --- Handle File Upload (if a new one is provided) ---
    if (in_array($type, ['file', 'video', 'audio', 'image'])) {
        if (isset($_FILES['file_path']) && $_FILES['file_path']['error'] === UPLOAD_ERR_OK) {

            $upload_dir = '../../uploads/materials/';
            $file_name = uniqid() . '-' . basename($_FILES['file_path']['name']);
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['file_path']['tmp_name'], $target_file)) {
                // New file was uploaded, set path for DB
                $file_path_sql = ", file_path = '" . $conn->real_escape_string('uploads/materials/' . $file_name) . "'";

                // Delete old file if it exists
                if (!empty($current_file_path) && file_exists('../../' . $current_file_path)) {
                    unlink('../../' . $current_file_path);
                }
            } else {
                $response['message'] = 'Failed to move uploaded file.';
                echo json_encode($response);
                exit;
            }
        }
    }

    // --- Update Database ---
    $sql = "UPDATE learning_materials SET label = ?, type = ?, link_url = ? $file_path_sql WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $label, $type, $link_url, $id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Material updated successfully.';
    } else {
        $response['message'] = 'Database error: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
exit;
