<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    exit;
}
$user_id = $_SESSION['user_id'];
$notification_id = $_POST['id'];

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $notification_id, $user_id);
$stmt->execute();
