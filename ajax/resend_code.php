<?php
// /ajax/resend_code.php

// --- THIS IS THE FIX ---
// This forces PHP to use the same timezone as your database.
date_default_timezone_set('Asia/Manila');
// --- END FIX ---

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load all our required files
require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/includes/db.php';

$response = [
    "success" => false,
    "message" => "An unknown error occurred."
];

// Get the email from the JSON payload
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response["message"] = "Invalid email provided.";
    echo json_encode($response);
    exit;
}

try {
    // Find the user
    $stmt_find = $conn->prepare("SELECT id, fname, is_verified FROM users WHERE email = ?");
    $stmt_find->bind_param("s", $email);
    $stmt_find->execute();
    $user_result = $stmt_find->get_result();

    if ($user_result->num_rows === 0) {
        $response["message"] = "This email is not registered.";
        echo json_encode($response);
        exit;
    }

    $user = $user_result->fetch_assoc();
    $stmt_find->close();

    if ($user['is_verified'] == 1) {
        $response["message"] = "This account is already verified. You can log in.";
        echo json_encode($response);
        exit;
    }

    // --- Generate and save new code ---
    $new_code = random_int(100000, 999999);
    // This time will now be in 'Asia/Manila' timezone
    $expiry_time = date('Y-m-d H:i:s', time() + 1800); // 30 minutes from now

    $stmt_update = $conn->prepare("UPDATE users SET verification_code = ?, code_expires_at = ? WHERE id = ?");
    $stmt_update->bind_param("ssi", $new_code, $expiry_time, $user['id']);
    $stmt_update->execute();
    $stmt_update->close();

    // --- Send the new code via email ---
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'als.learning.management.system@gmail.com'; // Your email
    $mail->Password   = 'cojk uoaw yjmm imcd'; // Your App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->setFrom('als.learning.management.system@gmail.com', 'ALS LMS');
    $mail->addAddress($email, $user['fname']);
    $mail->isHTML(true);

    $mail->Subject = 'Your New Verification Code - ALS LMS';
    $mail->Body    = "Hi " . htmlspecialchars($user['fname']) . ",<br><br>Your new 6-digit verification code is:<br><br><h1 style='text-align:center; letter-spacing: 5px;'>" . $new_code . "</h1><br>This code will expire in 30 minutes.";
    $mail->AltBody = "Your new 6-digit verification code is: " . $new_code;

    $mail->send();

    $response["success"] = true;
    $response["message"] = "A new verification code has been sent to your email.";
} catch (Exception $e) {
    $response["message"] = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
    error_log("PHPMailer Error: " . $e->getMessage());
}

$conn->close();
echo json_encode($response);
