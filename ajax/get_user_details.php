<?php
// FILE: ajax/get_user_details.php
session_start();
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/includes/db.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT fname, lname, avatar_url FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
echo json_encode($result->fetch_assoc());
