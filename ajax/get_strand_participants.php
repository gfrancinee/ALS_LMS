<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Security & Validation
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if (!isset($_GET['strand_id'])) {
    echo json_encode(['error' => 'Strand ID is required.']);
    exit;
}

$strand_id = $_GET['strand_id'];

$stmt = $conn->prepare("
    SELECT 
        u.id, u.fname, u.lname, u.avatar_url, 
        sp.role, sp.id AS participant_id 
    FROM users u
    JOIN strand_participants sp ON u.id = sp.student_id
    WHERE sp.strand_id = ?
    ORDER BY 
        sp.role ASC,      -- Keeps 'admin' at the top
        u.fname ASC,      -- Sorts by first name
        u.lname ASC       -- Sorts by last name
");

$stmt->bind_param("i", $strand_id);
if (!$stmt->execute()) {
    echo json_encode(['error' => 'Database query failed.']);
    exit;
}

$result = $stmt->get_result();
$participants = [];
while ($row = $result->fetch_assoc()) {
    $participants[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($participants);
