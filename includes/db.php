<?php
// Enable MySQLi error reporting
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

// --- ADDED TIMEZONE FIX for MySQLi ---
// Set the connection timezone to match PHP's timezone (Asia/Manila)
$conn->query("SET time_zone = '+8:00'");
// --- END FIX ---


// ==========================================================
// 2. PDO Connection (ADDED for the Course Oversight Dashboard)
// ==========================================================

// Create the $db variable that the ls.php dashboard is expecting (a PDO object)
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- ADDED TIMEZONE FIX for PDO ---
    // Set the connection timezone to match PHP's timezone (Asia/Manila)
    $db->exec("SET time_zone = '+8:00'");
    // --- END FIX ---

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

    $fname = 'ALS';
    $lname = 'LMS';
    $address = 'System';
    $phone = '00000000000';
    $role = 'admin';

    $stmt = $conn->prepare("INSERT INTO users (fname, lname, address, email, phone, password, role)
                             VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $fname, $lname, $address, $adminEmail, $phone, $hashedPassword, $role);
    $stmt->execute();
    // No need to close $stmt here if $check is closed later
}
$check->close(); // Close the $check statement

// Note: If the $stmt for INSERT was run, it should also be closed if it's not null.
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}

// Do not close $conn here, as other scripts include this file to *use* the connection.
// $conn->close();
