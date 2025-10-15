<?php
// /ajax/manage_material_categories.php (Final Version with Edit/Delete)
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$strand_id = $_POST['strand_id'] ?? $_GET['strand_id'] ?? 0;
$teacher_id = $_SESSION['user_id'];

switch ($action) {
    case 'fetch':
        $stmt = $conn->prepare("SELECT id, name FROM material_categories WHERE strand_id = ? AND teacher_id = ? ORDER BY name ASC");
        $stmt->bind_param("ii", $strand_id, $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $result]);
        break;

    case 'create':
        $name = trim($_POST['name'] ?? '');
        if (empty($name) || empty($strand_id)) {
            echo json_encode(['success' => false, 'error' => 'Category name is required.']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO material_categories (name, strand_id, teacher_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $name, $strand_id, $teacher_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'data' => ['id' => $stmt->insert_id, 'name' => $name]]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error.']);
        }
        break;

    case 'edit':
        $name = trim($_POST['name'] ?? '');
        $id = $_POST['id'] ?? 0;
        if (empty($name) || empty($id)) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE material_categories SET name = ? WHERE id = ? AND teacher_id = ?");
        $stmt->bind_param("sii", $name, $id, $teacher_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'data' => ['id' => $id, 'name' => $name]]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error.']);
        }
        break;

    case 'delete':
        $id = $_POST['id'] ?? 0;
        if (empty($id)) {
            echo json_encode(['success' => false, 'error' => 'Category ID is required.']);
            exit;
        }
        // Set materials in this category to be uncategorized
        $update_stmt = $conn->prepare("UPDATE learning_materials SET category_id = NULL WHERE category_id = ?");
        $update_stmt->bind_param("i", $id);
        $update_stmt->execute();
        $update_stmt->close();

        // Now, delete the category itself
        $stmt = $conn->prepare("DELETE FROM material_categories WHERE id = ? AND teacher_id = ?");
        $stmt->bind_param("ii", $id, $teacher_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
