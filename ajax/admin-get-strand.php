<?php
// Make sure this path is correct for your structure
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

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);

    // --- THIS IS THE CORRECTED QUERY ---
    // It now includes `grade_level`
    $stmt = $conn->prepare("SELECT id, strand_title, strand_code, grade_level FROM learning_strands WHERE id = ?");

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $strand = $result->fetch_assoc();
            $response['success'] = true;
            $response['data'] = $strand; // This will now include grade_level
        } else {
            $response['message'] = 'Strand not found.';
        }
    } else {
        $response['message'] = 'Database error: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $response['message'] = 'Invalid Strand ID.';
}

$conn->close();
echo json_encode($response);
exit;
