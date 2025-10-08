<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Basic security check: ensure user is a logged-in teacher
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$teacher_id = $_SESSION['user_id'];
// You need to pass the strand_id from your page to this script
$strand_id = $_POST['strand_id'] ?? $_GET['strand_id'] ?? 0;

if (empty($strand_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Strand ID is required.']);
    exit;
}

switch ($action) {
    case 'add':
        $name = trim($_POST['category_name'] ?? '');
        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO assessment_categories (name, teacher_id, strand_id) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $name, $teacher_id, $strand_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $conn->insert_id]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Category name cannot be empty']);
        }
        break;

    case 'fetch':
        $stmt = $conn->prepare("SELECT id, name FROM assessment_categories WHERE teacher_id = ? AND strand_id = ? ORDER BY name");
        $stmt->bind_param("ii", $teacher_id, $strand_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $categories = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($categories);
        break;

        // You can add 'delete' and 'update' cases here in the future
}
