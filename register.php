<?php
// Start session and include PHPMailer classes
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If using Composer:
require 'vendor/autoload.php';
// If not, adjust paths to your PHPMailer files:
// require 'includes/PHPMailer/Exception.php';
// require 'includes/PHPMailer/PHPMailer.php';
// require 'includes/PHPMailer/SMTP.php';

require_once 'includes/db.php';

// Initialize variables to hold error messages
$error_message = '';
$success_message = '';

// --- FORM PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitize and retrieve form data
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    // 2. Check if email already exists
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $error_message = "An account with this email already exists. Please <a href='login.php'>log in</a>.";
        $stmt_check->close();
    } else {
        $stmt_check->close();

        // 3. Hash the password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 4. Generate a unique verification token
        $token = bin2hex(random_bytes(32));

        // 5. Save user to the database with the token
        // IMPORTANT: Ensure your `users` table has all these columns!
        $stmt = $conn->prepare("INSERT INTO users (fname, lname, address, email, phone, password, role, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $fname, $lname, $address, $email, $phone, $hashed_password, $role, $token);

        if ($stmt->execute()) {
            // --- 6. SEND VERIFICATION EMAIL ---
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
                $mail->setFrom('your_email@gmail.com', 'ALS LMS'); // "From" address
                $mail->addAddress($email, $fname . ' ' . $lname);     // User's email

                //Content
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your Account - ALS LMS';
                $verification_link = "http://localhost/ALS_LMS/verify.php?token=" . $token;
                $mail->Body    = "Hi $fname,<br><br>Thank you for registering! Please click the link below to verify your email address:<br><br><a href='$verification_link'>Verify Account</a>";
                $mail->AltBody = 'Please copy and paste this link into your browser to verify your account: ' . $verification_link;

                $mail->send();

                // Redirect to a page that tells the user to check their email
                header("Location: registration_success.php");
                exit();
            } catch (Exception $e) {
                // This will display the specific technical error directly on your form
                $error_message = "Mailer Error: " . $mail->ErrorInfo;
            }
        } else {
            // Handle database insertion error
            $error_message = "Error during registration. Please try again. Error: " . $stmt->error;
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ALS Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/register.css" />
    <script src="js/register.js" defer></script>
</head>

<body class="bg-light">
    <main class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="register-container">
                    <header>
                        <h1 class="text-center">Registration Form</h1>
                    </header>

                    <?php
                    // Display any error messages here
                    if (!empty($error_message)) {
                        echo '<div class="alert alert-danger" role="alert">' . $error_message . '</div>';
                    }
                    ?>

                    <form id="registerForm" method="POST" action="register.php" novalidate>
                        <div class="mb-3">
                            <label for="fname" class="form-label">Firstname</label>
                            <input type="text" class="form-control" id="fname" name="fname" required>
                        </div>

                        <div class="mb-3">
                            <label for="lname" class="form-label">Lastname</label>
                            <input type="text" class="form-control" id="lname" name="lname" required>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="address" name="address" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="" disabled selected>Select your role</option>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                            </select>
                        </div>

                        <button id="registerBtn" type="submit" class="btn btn-primary w-100">
                            <span class="btn-text">Register</span>
                            <span class="spinner-border spinner-border-sm text-light ms-2 spinner" role="status" style="display:none;"></span>
                        </button>
                    </form>

                    <p class="text-center mt-3">
                        Already registered? <a href="login.php">Log In</a>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>