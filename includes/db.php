<?php
// Enable MySQLi error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database credentials
$host = 'localhost';
$db   = 'als_lms';
$user = 'root';
$pass = ''; // default for XAMPP

// Connect to database
$conn = new mysqli($host, $user, $pass, $db);

// Handle connection errors with JSON-safe output
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Optional: Auto-create admin user if not found
$adminEmail = 'admin@gmail.com';
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
