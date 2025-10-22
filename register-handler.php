<?php
// FILE: register-handler.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json'); // Tell the browser we are sending JSON

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader for PHPMailer
require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/includes/db.php';

$response = [
    "status" => "error",
    "message" => "An unknown error occurred.",
    "errors" => [] // For field-specific errors
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

    // --- Start Validation ---
    if (empty($fname)) $response["errors"]["fname"] = "Firstname is required.";
    if (empty($lname)) $response["errors"]["lname"] = "Lastname is required.";
    if (empty($address)) $response["errors"]["address"] = "Address is required.";
    if (empty($email)) $response["errors"]["email"] = "Email is required.";
    if (empty($phone)) $response["errors"]["phone"] = "Phone is required.";
    if (empty($password)) $response["errors"]["password"] = "Password is required.";
    if (empty($role)) $response["errors"]["role"] = "Role is required.";

    if ($password !== $confirmPassword) {
        $response["errors"]["confirmPassword"] = "Passwords do not match.";
    }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["errors"]["email"] = "Invalid email format.";
    }
    if ($password && strlen($password) < 6) {
        $response["errors"]["password"] = "Password must be at least 6 characters.";
    }

    // If there are any errors, stop and send them back
    if (!empty($response["errors"])) {
        $response["message"] = "Please fix the errors below.";
        echo json_encode($response);
        exit;
    }
    // --- End Validation ---

    // Check if email already exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $response["errors"]["email"] = "This email is already registered.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // --- THIS IS THE NEW LOGIC ---
        // 1. Generate a 6-digit code and expiration time
        $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes')); // Code is valid for 15 minutes

        // 2. Insert the user with the new code
        $insert_stmt = $conn->prepare(
            "INSERT INTO users (fname, lname, address, email, phone, password, role, verification_code, code_expires_at, is_verified) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
        );
        $insert_stmt->bind_param("sssssssss", $fname, $lname, $address, $email, $phone, $hashedPassword, $role, $verification_code, $expires_at);

        if ($insert_stmt->execute()) {
            $mail = new PHPMailer(true);
            try {
                // --- Server settings (using your provided details) ---
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'als.learning.management.system@gmail.com';
                $mail->Password   = 'cojk uoaw yjmm imcd'; // Your App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                // --- Recipients ---
                $mail->setFrom('als.learning.management.system@gmail.com', 'ALS LMS');
                $mail->addAddress($email, $fname . ' ' . $lname);

                // --- Content ---
                $mail->isHTML(true);
                $mail->Subject = 'Your ALS Verification Code';
                // 3. Email the 6-digit code
                $mail->Body    = "Hi $fname,<br><br>Thank you for registering! Your verification code is:<br><br><h1 style='font-size: 32px; letter-spacing: 5px; text-align: center;'>$verification_code</h1><br>This code will expire in 15 minutes.";
                $mail->AltBody = "Your verification code is: $verification_code. This code will expire in 15 minutes.";

                $mail->send();

                // 4. Send a new status to the JavaScript
                $response["status"] = "success_code_sent"; // This is the new status
                $response["message"] = "Registration successful! Please check your email for a verification code.";
                $response["email"] = $email; // Send the email back
            } catch (Exception $e) {
                $response["message"] = "Registration succeeded, but the verification email could not be sent. Please contact support. Mailer Error: " . $mail->ErrorInfo;
            }
        } else {
            $response["message"] = "Database error: " . $insert_stmt->error;
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
}

$conn->close();
echo json_encode($response); // Send the JSON response back to the JavaScript
