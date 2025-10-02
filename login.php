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
        $stmt = $conn->prepare("SELECT id, fname, role, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
    }

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $fname, $role, $hashedPassword);
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
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
            $message = "Incorrect password.";
        }
    } else {
        $message = "No account found with that email.";
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
    <div class="container-fluid d-flex justify-content-end align-items-center vh-100 p-0">
        <!-- Logo Column -->
        <div class="logos d-flex flex-column align-items-center me-5 pe-5">
            <img src="img/BNHS.jpg" alt="school" class="logo circular mb-3" />
            <img src="img/ALS.png" alt="ALS" class="logo circular" />
        </div>

        <!-- Login Form Column -->
        <main class="login-container">
            <header class="mb-4 text-center">
                <h1 id="font">
                    <span>A</span><span>L</span><span>S</span> Learning Management System
                </h1>
            </header>

            <?php if ($message): ?>
                <section id="message" role="alert" aria-live="polite" class="text-danger text-center mb-3">
                    <?= htmlspecialchars($message) ?>
                </section>
            <?php endif; ?>

            <form id="loginForm" method="POST" novalidate>
                <div class="email">
                    <label for="email" class="form-label">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="email@example.com"
                        required />
                    <div class="error-message" id="emailError" aria-live="assertive"></div>
                </div>

                <div class="password">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="password"
                        required />
                    <div class="error-message" id="passwordError" aria-live="assertive"></div>
                </div>

                <div class="submit mb-3">
                    <button id="loginBtn" type="submit" class="btn w-100">
                        <span class="btn-text">Log In</span>
                        <span class="spinner-border spinner-border-sm text-light" role="status" style="display:none;"></span>
                    </button>
                </div>
            </form>

            <div class="register-link text-center mt-3">
                <p>Not yet registered? <a href="register.php">Register</a></p>
            </div>
        </main>
    </div>
</body>

</html>