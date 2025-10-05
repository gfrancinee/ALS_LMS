<?php
// FILE: register-handler.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json'); // Tell the browser we are sending JSON

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/includes/db.php';

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

    if (empty($fname) || empty($lname) || empty($address) || empty($email) || empty($phone) || empty($password) || empty($role)) {
        $response["message"] = "All fields are required.";
    } elseif ($password !== $confirmPassword) {
        $response["message"] = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["message"] = "Invalid email format.";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $response["message"] = "Email is already registered.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(32));

            $insert_stmt = $conn->prepare("INSERT INTO users (fname, lname, address, email, phone, password, role, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("ssssssss", $fname, $lname, $address, $email, $phone, $hashedPassword, $role, $token);

            if ($insert_stmt->execute()) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'als.learning.management.system@gmail.com';
                    $mail->Password   = 'cojk uoaw yjmm imcd'; // IMPORTANT: USE YOUR REAL APP PASSWORD
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = 465;

                    $mail->setFrom('als.learning.management.system@gmail.com', 'ALS LMS');
                    $mail->addAddress($email, $fname . ' ' . $lname);

                    $mail->isHTML(true);
                    $mail->Subject = 'Verify Your Account - ALS LMS';
                    $verification_link = "http://localhost/ALS_LMS/verify.php?token=" . $token;
                    $mail->Body    = "Hi $fname,<br><br>Thank you for registering! Please click the link below to activate your account:<br><br><a href='$verification_link'>Verify Account</a>";

                    $mail->send();

                    $response["status"] = "success";
                    $response["message"] = "Registration successful! Please check your email to verify your account.";
                    $response["role"] = $role;
                } catch (Exception $e) {
                    $response["message"] = "Registration succeeded, but email failed: " . $mail->ErrorInfo;
                }
            } else {
                $response["message"] = "Database error: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

$conn->close();
echo json_encode($response); // Send the JSON response back to the JavaScript
