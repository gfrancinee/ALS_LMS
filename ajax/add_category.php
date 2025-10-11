<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security Check: User must be a logged-in teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$strand_id = $_POST['strand_id'] ?? 0;
$category_name = trim($_POST['category_name'] ?? '');

if (empty($strand_id) || empty($category_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO categories (strand_id, name) VALUES (?, ?)");
    $stmt->bind_param("is", $strand_id, $category_name);

    if ($stmt->execute()) {
        $new_category_id = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'newCategory' => [
                'id' => $new_category_id,
                'name' => htmlspecialchars($category_name) // Sanitize output
            ]
        ]);
    } else {
        throw new Exception("Database execution failed.");
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
