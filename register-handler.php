<?php
session_start();
header('Content-Type: application/json');

// --- SETUP AND INCLUDES ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If using Composer:
require 'vendor/autoload.php';
// If not using Composer, adjust the path to your PHPMailer files:
// require 'includes/PHPMailer/Exception.php';
// require 'includes/PHPMailer/PHPMailer.php';
// require 'includes/PHPMailer/SMTP.php';

require_once 'includes/db.php';

$response = [
    "status" => "error",
    "message" => "An unknown error occurred."
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // --- 1. VALIDATION (Your original logic is great) ---
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $role = $_POST['role'] ?? '';

    if (!$fname || !$lname || !$address || !$email || !$phone || !$password || !$confirmPassword || !$role) {
        $response["message"] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["message"] = "Invalid email format.";
    } elseif ($password !== $confirmPassword) {
        $response["message"] = "Passwords do not match.";
    } else {
        // --- 2. CHECK FOR EXISTING EMAIL ---
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $response["message"] = "Email is already registered.";
        } else {
            // --- 3. PREPARE USER DATA FOR DATABASE ---
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(32)); // Generate secure verification token

            // **MODIFIED SQL QUERY** to include the verification_token
            $stmt = $conn->prepare("INSERT INTO users (fname, lname, address, email, phone, password, role, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $fname, $lname, $address, $email, $phone, $hashedPassword, $role, $token);

            if ($stmt->execute()) {
                // --- 4. SEND VERIFICATION EMAIL ---
                $mail = new PHPMailer(true);
                try {
                    //Server settings - Replace with your details
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'your_email@gmail.com'; // Your Gmail address
                    $mail->Password   = 'zubb muaw haur hmib'; // Your Google App Password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = 465;

                    //Recipients
                    $mail->setFrom('your_email@gmail.com', 'ALS LMS');
                    $mail->addAddress($email, $fname . ' ' . $lname);

                    //Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Verify Your Account - ALS LMS';
                    // Make sure this URL is correct for your project
                    $verification_link = "http://localhost/ALS_LMS/verify.php?token=" . $token;
                    $mail->Body    = "Hi $fname,<br><br>Thank you for registering! Please click the link below to activate your account:<br><br><a href='$verification_link'>Verify Account</a>";
                    $mail->AltBody = 'Please copy and paste this link into your browser to verify your account: ' . $verification_link;

                    $mail->send();

                    // **MODIFIED SUCCESS RESPONSE**
                    $response["status"] = "success";
                    $response["message"] = "Registration successful! Please check your email to verify your account.";
                } catch (Exception $e) {
                    // Email failed to send, but user is in DB. This is a critical error.
                    $response["message"] = "Registration succeeded, but the verification email could not be sent. Please contact support.";
                }
            } else {
                $response["message"] = "Database insertion failed: " . $stmt->error;
            }
        }
    }
}

echo json_encode($response);
