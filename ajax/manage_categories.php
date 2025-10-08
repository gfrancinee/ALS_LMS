<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$teacher_id = $_SESSION['user_id'];
$response = ['success' => false];

switch ($action) {
    case 'update':
        $name = trim($_POST['category_name'] ?? '');
        $id = $_POST['category_id'] ?? 0;
        if (!empty($name) && !empty($id)) {
            $stmt = $conn->prepare("UPDATE assessment_categories SET name = ? WHERE id = ? AND teacher_id = ?");
            $stmt->bind_param("sii", $name, $id, $teacher_id);
            if ($stmt->execute()) {
                // Send back a success message AND the new name
                $response['success'] = true;
                $response['updatedName'] = $name;
            }
            $stmt->close();
        }
        break;

    case 'delete':
        $id = $_POST['category_id'] ?? 0;
        if (!empty($id)) {
            // First, un-categorize assessments
            $stmt1 = $conn->prepare("UPDATE assessments SET category_id = NULL WHERE category_id = ? AND teacher_id = ?");
            $stmt1->bind_param("ii", $id, $teacher_id);
            $stmt1->execute();
            $stmt1->close();

            // Then, delete the category
            $stmt2 = $conn->prepare("DELETE FROM assessment_categories WHERE id = ? AND teacher_id = ?");
            $stmt2->bind_param("ii", $id, $teacher_id);
            if ($stmt2->execute()) $response['success'] = true;
            $stmt2->close();
        }
        break;
}

echo json_encode($response);
$conn->close();
