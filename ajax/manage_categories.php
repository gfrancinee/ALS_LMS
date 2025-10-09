<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Get the action from GET if it exists, otherwise from POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action !== 'fetch' && (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$teacher_id = $_SESSION['user_id'] ?? 0;
$response = ['success' => false];

switch ($action) {
    // NEW: This case fetches the list of categories
    case 'fetch':
        $strand_id = $_GET['strand_id'] ?? 0;
        if (!empty($strand_id)) {
            $stmt = $conn->prepare("SELECT id, name FROM assessment_categories WHERE strand_id = ? ORDER BY name");
            $stmt->bind_param("i", $strand_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $response = ['success' => true, 'data' => $data];
            $stmt->close();
        }
        break;

    case 'create':
        $name = trim($_POST['category_name'] ?? '');
        $strand_id = $_POST['strand_id'] ?? 0;
        if (!empty($name) && !empty($strand_id)) {
            $stmt = $conn->prepare("INSERT INTO assessment_categories (name, strand_id, teacher_id) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $name, $strand_id, $teacher_id);
            if ($stmt->execute()) {
                // Get the ID of the new category
                $newId = $conn->insert_id;
                $response['success'] = true;
                // Send back the new category's data
                $response['newCategory'] = ['id' => $newId, 'name' => $name];
            } else {
                $response['error'] = 'Database error during creation.';
            }
            $stmt->close();
        } else {
            $response['error'] = 'Category name or Strand ID was missing.';
        }
        break;

    // ... your 'update' and 'delete' cases remain the same ...
    case 'update':
        $name = trim($_POST['category_name'] ?? '');
        $id = $_POST['category_id'] ?? 0;
        if (!empty($name) && !empty($id)) {
            $stmt = $conn->prepare("UPDATE assessment_categories SET name = ? WHERE id = ? AND teacher_id = ?");
            $stmt->bind_param("sii", $name, $id, $teacher_id);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['updatedName'] = $name;
            }
            $stmt->close();
        }
        break;

    case 'delete':
        $id = $_POST['category_id'] ?? 0;
        if (!empty($id)) {
            $stmt1 = $conn->prepare("UPDATE assessments SET category_id = NULL WHERE category_id = ? AND teacher_id = ?");
            $stmt1->bind_param("ii", $id, $teacher_id);
            $stmt1->execute();
            $stmt1->close();
            $stmt2 = $conn->prepare("DELETE FROM assessment_categories WHERE id = ? AND teacher_id = ?");
            $stmt2->bind_param("ii", $id, $teacher_id);
            if ($stmt2->execute()) $response['success'] = true;
            $stmt2->close();
        }
        break;
}

echo json_encode($response);
$conn->close();
