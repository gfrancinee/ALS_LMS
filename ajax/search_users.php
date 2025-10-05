<?php
// FILE: ajax/search_users.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get the search term from the JavaScript fetch request
$searchTerm = $_GET['term'] ?? '';

if (empty($searchTerm)) {
    echo json_encode([]); // Return an empty array if search is empty
    exit();
}

$current_user_id = $_SESSION['user_id'];
$users = [];
$searchTerm = "%{$searchTerm}%"; // Prepare the search term for a LIKE query

// Find users whose first or last name matches the search term
// Exclude the current user from the results
$stmt = $conn->prepare("
    SELECT id, fname, lname, avatar_url 
    FROM users 
    WHERE (fname LIKE ? OR lname LIKE ?) AND id != ?
    LIMIT 10
");
$stmt->bind_param("ssi", $searchTerm, $searchTerm, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($users);
