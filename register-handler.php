<?php
// FILE: register-handler.php
date_default_timezone_set('Asia/Manila');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader for PHPMailer
require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ALS_LMS/includes/db.php';

$response = [
    "status" => "error",
    "message" => "An unknown error occurred.",
    "errors" => []
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

    // --- NEW FIELDS ---
    $gradeLevel = trim($_POST['gradeLevel'] ?? '');
    $lrn = trim($_POST['lrn'] ?? '');
    $isAdminVerified = 0; // Default to 0

    // --- Start Validation ---
    if (empty($fname)) $response["errors"]["fname"] = "Firstname is required.";
    if (empty($lname)) $response["errors"]["lname"] = "Lastname is required.";
    if (empty($address)) $response["errors"]["address"] = "Address is required.";
    if (empty($email)) $response["errors"]["email"] = "Email is required.";
    if (empty($phone)) $response["errors"]["phone"] = "Phone is required.";
    if (empty($password)) $response["errors"]["password"] = "Password is required.";
    if (empty($role)) $response["errors"]["role"] = "Role is required.";

    // STUDENT SPECIFIC VALIDATION
    if ($role === 'student') {
        if (empty($gradeLevel)) {
            $response["errors"]["gradeLevel"] = "Grade level is required for students.";
        }
        // Validate LRN (Must be exactly 12 digits)
        if (empty($lrn)) {
            $response["errors"]["lrn"] = "LRN is required.";
        } elseif (!preg_match('/^[0-9]{12}$/', $lrn)) {
            $response["errors"]["lrn"] = "LRN must be exactly 12 digits.";
        }

        $isAdminVerified = 0; // Students are Pending by default
    } else {
        // Teachers are Auto-Verified (or handled differently)
        $isAdminVerified = 1;
        $gradeLevel = NULL; // Ensure NULL for teachers
        $lrn = NULL;        // Ensure NULL for teachers
    }

    if ($password !== $confirmPassword) {
        $response["errors"]["confirmPassword"] = "Passwords do not match.";
    }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["errors"]["email"] = "Invalid email format.";
    }
    if ($password && strlen($password) < 6) {
        $response["errors"]["password"] = "Password must be at least 6 characters.";
    }

    // Return errors if any
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
        // Optional: Check if LRN already exists (to prevent duplicates)
        if ($role === 'student') {
            $lrn_check = $conn->prepare("SELECT id FROM users WHERE lrn = ?");
            $lrn_check->bind_param("s", $lrn);
            $lrn_check->execute();
            if ($lrn_check->get_result()->num_rows > 0) {
                $response["errors"]["lrn"] = "This LRN is already registered.";
                echo json_encode($response);
                exit;
            }
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // 1. Generate a 6-digit code and expiration time
        $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        // 2. Insert the user
        // UPDATED SQL: Added `lrn` and `is_admin_verified`
        $insert_stmt = $conn->prepare(
            "INSERT INTO users (fname, lname, address, email, phone, password, role, grade_level, lrn, is_admin_verified, verification_code, code_expires_at, is_verified) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
        );
        // Type string updated: "sssssssssiss" (added string for lrn, int for is_admin_verified)
        $insert_stmt->bind_param("sssssssssiss", $fname, $lname, $address, $email, $phone, $hashedPassword, $role, $gradeLevel, $lrn, $isAdminVerified, $verification_code, $expires_at);

        if ($insert_stmt->execute()) {
            $mail = new PHPMailer(true);
            try {
                // --- Server settings ---
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'als.learning.management.system@gmail.com';
                $mail->Password   = 'cojk uoaw yjmm imcd';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                // --- Recipients ---
                $mail->setFrom('als.learning.management.system@gmail.com', 'ALS LMS');
                $mail->addAddress($email, $fname . ' ' . $lname);

                // --- Content ---
                $mail->isHTML(true);
                $mail->Subject = 'Your ALS Verification Code';
                $mail->Body    = "Hi $fname,<br><br>Thank you for registering! Your verification code is:<br><br><h1 style='font-size: 32px; letter-spacing: 5px; text-align: center;'>$verification_code</h1><br>This code will expire in 15 minutes.";
                $mail->AltBody = "Your verification code is: $verification_code. This code will expire in 15 minutes.";

                $mail->send();

                // 4. Send status back
                $response["status"] = "success_code_sent";
                $response["message"] = "Registration successful! Please check your email for a verification code.";
                $response["email"] = $email;
            } catch (Exception $e) {
                $response["message"] = "Registration succeeded, but the verification email could not be sent. Mailer Error: " . $mail->ErrorInfo;
            }
        } else {
            $response["message"] = "Database error: " . $insert_stmt->error;
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
}

$conn->close();
echo json_encode($response);
