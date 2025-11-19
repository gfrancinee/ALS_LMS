<?php
session_start();
include 'includes/db.php'; // Connect to your als_lms database

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $message = "Please fill in both email and password.";
    } else {
        // --- FIX #1: Select the 'is_verified' column ---
        $stmt = $conn->prepare("SELECT id, fname, role, password, is_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        // --- FIX #2: Moved all logic inside this 'else' block ---
        if ($stmt->num_rows === 1) {

            // --- FIX #3: Bind the new '$is_verified' variable ---
            $stmt->bind_result($id, $fname, $role, $hashedPassword, $is_verified);
            $stmt->fetch();

            if (password_verify($password, $hashedPassword)) {

                // --- FIX #4: Add this new check for 'is_verified' ---
                if ($is_verified == 1) {
                    // SUCCESS! Password is correct AND account is verified
                    $_SESSION['user_id'] = $id;
                    $_SESSION['fname'] = $fname;
                    $_SESSION['role'] = $role;

                    switch ($role) {
                        case 'student':
                            header("Location: student/student.php");
                            break;
                        case 'teacher':
                            header("Location: teacher/teacher.php");
                            break;
                        case 'admin':
                            header("Location: admin/admin.php");
                            break;
                        default:
                            $message = "Unknown role. Access denied.";
                            break;
                    }
                    exit;
                } else {
                    // Password correct, but account not verified
                    $message = "Your account is not verified. Please check your email or <a href='verify.php?email=" . urlencode($email) . "'>resend the code</a>.";
                }
                // --- END OF FIX #4 ---

            } else {
                $message = "Incorrect password.";
            }
        } else {
            $message = "No account found with that email.";
        }
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>LMS Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css" />
    <script src="js/lg.js" defer></script>
</head>

<body>
    <div class="login-wrapper">
        <main class="login-card">

            <header class="text-center">
                <div class="brand-title">
                    <h1 id="font">
                        <span>A</span><span>L</span><span>S</span> LMS
                    </h1>
                </div>
                <p class="brand-subtitle">Sign in to your account</p>
            </header>

            <?php if ($message): ?>
                <div id="message" role="alert" aria-live="polite" class="alert alert-modern d-flex align-items-center p-3 mb-4">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <span><?= html_entity_decode($message) ?></span>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" novalidate>
                <div class="form-group email">
                    <label for="email" class="form-label">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="name@example.com"
                        required />
                    <div class="error-message text-danger small mt-1" id="emailError" aria-live="assertive"></div>
                </div>

                <div class="form-group password">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        required />
                    <div class="error-message text-danger small mt-1" id="passwordError" aria-live="assertive"></div>
                </div>

                <div class="submit mt-4">
                    <button id="loginBtn" type="submit" class="btn w-100 btn-primary rounded-pill btn-login">
                        <span class="btn-text">Log In</span>
                        <span class="spinner-border spinner-border-sm text-light ms-2" role="status" style="display:none;"></span>
                    </button>
                </div>
            </form>

            <div class="register-link text-center mt-4">
                <p class="register-text">Don't have an account? <a href="register.php">Create Account</a></p>
            </div>

        </main>
    </div>
</body>

</html>