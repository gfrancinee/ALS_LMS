<?php
require_once '../../includes/db.php';

// --- ADDED: Prepare for a JSON response ---
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Check if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitize and validate inputs
    $id     = intval($_POST['id'] ?? 0);
    $fname  = trim($_POST['fname'] ?? '');
    $lname  = trim($_POST['lname'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $role   = trim($_POST['role'] ?? '');

    // --- ADDED: Get new fields from the modal ---
    $grade_level = trim($_POST['grade_level'] ?? '');
    $password    = trim($_POST['password'] ?? '');

    // --- UPDATED: Basic validation ---
    if ($id <= 0 || empty($fname) || empty($lname) || empty($email) || empty($role)) {
        $response['message'] = 'Invalid input. Please fill out all required fields.';
        echo json_encode($response);
        exit;
    }

    // --- ADDED: Password validation (if provided) ---
    if (!empty($password) && strlen($password) < 6) {
        $response['message'] = 'Password must be at least 6 characters.';
        echo json_encode($response);
        exit;
    }

    // --- ADDED: Grade Level logic ---
    // Store NULL in the database if the role is 'teacher' or if 'N/A' was selected
    $grade_level_to_db = ($role === 'teacher' || empty($grade_level)) ? NULL : $grade_level;

    // --- UPDATED: Dynamic SQL Query ---
    $params = [];
    $types = "";

    if (!empty($password)) {
        // --- A. Query to update password ---
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET fname = ?, lname = ?, email = ?, role = ?, grade_level = ?, password = ? WHERE id = ?";

        $params = [$fname, $lname, $email, $role, $grade_level_to_db, $hashed_password, $id];
        $types = "ssssssi"; // s = string, i = integer
    } else {
        // --- B. Query to update WITHOUT password ---
        $sql = "UPDATE users SET fname = ?, lname = ?, email = ?, role = ?, grade_level = ? WHERE id = ?";

        $params = [$fname, $lname, $email, $role, $grade_level_to_db, $id];
        $types = "sssssi";
    }

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        $response['message'] = 'Database error (prepare): ' . $conn->error;
        echo json_encode($response);
        exit;
    }

    // Use the "splat" operator (...) to pass the array of params
    $stmt->bind_param($types, ...$params);

    // --- UPDATED: Execute and provide JSON response ---
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'User updated successfully.';
    } else {
        // Check for duplicate email
        if ($conn->errno == 1062) {
            $response['message'] = 'This email address is already in use by another account.';
        } else {
            $response['message'] = 'Error updating user: ' . $stmt->error;
        }
    }
    $stmt->close();
} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
// --- UPDATED: Send final JSON response ---
echo json_encode($response);
exit;
