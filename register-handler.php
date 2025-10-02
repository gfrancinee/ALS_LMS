<?php
session_start();
header('Content-Type: application/json');
require_once 'includes/db.php';

$response = [
    "status" => "error",
    "message" => "An unknown error occurred."
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $role = $_POST['role'] ?? '';

    // Validation (Your original validation logic is perfect)
    if (!$fname || !$lname || !$address || !$email || !$phone || !$password || !$confirmPassword || !$role) {
        $response["message"] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["message"] = "Invalid email format.";
    } elseif ($password !== $confirmPassword) {
        $response["message"] = "Passwords do not match.";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $response["message"] = "Email is already registered.";
        } else {
            // Hash the password and insert the new user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (fname, lname, address, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $fname, $lname, $address, $email, $phone, $hashedPassword, $role);

            if ($stmt->execute()) {
                // Get the ID of the new user we just created
                $new_user_id = $conn->insert_id;

                // Set session variables to automatically log them in
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['role'] = $role;
                $_SESSION['fname'] = $fname;

                // Prepare the success response
                $response["status"] = "success";
                $response["message"] = "Registered successfully!";
                $response["role"] = $role; // Send role back to JS for redirection
            } else {
                $response["message"] = "Registration failed. Please try again.";
            }
        }
    }
}

echo json_encode($response);
