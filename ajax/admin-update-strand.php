<?php
// UPDATED PATH: ../includes/
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An error occurred.'];

// Only admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $response['message'] = 'Unauthorized.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = intval($_POST['id'] ?? 0);
    $title = trim($_POST['strand_title'] ?? '');
    $code = trim($_POST['strand_code'] ?? '');
    $grade_level = trim($_POST['grade_level'] ?? '');

    $grade_level_to_db = !empty($grade_level) ? $grade_level : NULL;

    if ($id > 0 && !empty($title) && !empty($code)) {

        $stmt = $conn->prepare("UPDATE learning_strands SET strand_title = ?, strand_code = ?, grade_level = ? WHERE id = ?");
        $stmt->bind_param("sssi", $title, $code, $grade_level_to_db, $id);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Learning strand updated successfully.';
        } else {
            if ($conn->errno == 1062) {
                $response['message'] = 'This Strand Code is already in use by another strand.';
            } else {
                $response['message'] = 'Database error: ' . $stmt->error;
            }
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid data provided. Title and Code are required.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
exit;
