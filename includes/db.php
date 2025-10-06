<?php
require_once 'functions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database credentials
$host = 'localhost';
$dbname = 'als_lms'; // Renamed $db to $dbname to avoid conflict with $db for PDO
$user = 'root';
$pass = ''; // default for XAMPP

// ==========================================================
// 1. MySQLi Connection (Used by your existing system functions)
// ==========================================================

// Connect to database (Uses $dbname)
$conn = new mysqli($host, $user, $pass, $dbname);

// Handle connection errors with JSON-safe output
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// ==========================================================
// 2. PDO Connection (ADDED for the Course Oversight Dashboard)
// ==========================================================

// Create the $db variable that the ls.php dashboard is expecting (a PDO object)
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If PDO connection fails, set $db to null so ls.php handles the error gracefully
    $db = null;
    // You could also log the error here if needed: error_log($e->getMessage());
}

// ==========================================================
// 3. Original Logic (Remains unchanged)
// ==========================================================

// Optional: Auto-create admin user if not found
$adminEmail = 'als.learning.management.system@gmail.com';
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $adminEmail);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);

    $fname = 'Admin';
    $lname = 'User';
    $address = 'System';
    $phone = '00000000000';
    $role = 'admin';

    $stmt = $conn->prepare("INSERT INTO users (fname, lname, address, email, phone, password, role)
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $fname, $lname, $address, $adminEmail, $phone, $hashedPassword, $role);
    $stmt->execute();
}
