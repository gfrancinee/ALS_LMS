<?php
require_once '../includes/db.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = intval($_POST['id'] ?? 0);

    if ($id > 0) {
        // --- UPDATED QUERY: Set BOTH is_verified AND is_admin_verified to 1 ---
        // This ensures the Email is marked valid AND the Admin has approved the LRN
        $stmt = $conn->prepare("UPDATE users SET is_verified = 1, is_admin_verified = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'User fully verified successfully.';
        } else {
            $response['message'] = 'Database error: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid user ID provided.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
exit;
