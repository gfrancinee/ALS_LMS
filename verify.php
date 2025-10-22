<?php
session_start();
require_once 'includes/db.php';

$error_message = '';
$success_message = '';
// Get the email from the URL (sent by register.js)
$user_email = $_GET['email'] ?? '';

// This block handles the form submission when the user enters the 6-digit code
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['verification_code'] ?? '';
    $email = $_POST['email'] ?? ''; // Get email from the hidden field

    if (empty($code) || empty($email)) {
        $error_message = "Please enter the 6-digit code.";
    } else {
        // Find the user and check if the code is correct AND not expired
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND verification_code = ? AND code_expires_at > NOW()");
        $stmt->bind_param("ss", $email, $code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // SUCCESS! Code is correct and valid.
            $stmt->close();

            // 1. Mark user as verified
            $stmt_update = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL, code_expires_at = NULL WHERE email = ?");
            $stmt_update->bind_param("s", $email);
            $stmt_update->execute();
            $stmt_update->close();

            // 2. Set flag to show success modal
            $success_message = "Verification Successful! You can now log in.";
        } else {
            // FAILED! Code is wrong or expired.
            $stmt->close();
            $error_message = "Invalid or expired verification code. Please try again.";
        }
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Email Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/register.css" /> <!-- Reuse your register CSS -->
</head>

<body class="bg-light">
    <main class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="register-container">
                    <header>
                        <h1 class="text-center">Verify Your Account</h1>
                        <p class="text-center text-muted">
                            A 6-digit code was sent to <br><strong><?= htmlspecialchars($user_email) ?></strong>
                        </p>
                    </header>

                    <form id="verifyForm" method="POST" action="verify.php?email=<?= htmlspecialchars($user_email) ?>">
                        <!-- Send the email along with the form submission -->
                        <input type="hidden" name="email" value="<?= htmlspecialchars($user_email) ?>">

                        <!-- Show server error message here -->
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger text-center"><?= $error_message ?></div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="verification_code" class="form-label">Verification Code</label>
                            <input type="text" class="form-control" id="verification_code" name="verification_code" required maxlength="6"
                                style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5rem;" autofocus>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Verify
                        </button>
                    </form>

                    <p class="text-center mt-3">
                        <!-- Note: You will need to create 'resend-code.php' later -->
                        Didn't get a code? <a href="resend-code.php?email=<?= htmlspecialchars($user_email) ?>">Resend code</a>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- This is the Success Modal from your screenshot -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verification Successful!</h5>
                </div>
                <div class="modal-body">
                    <p>Your account is now verified. You can log in.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="goLoginBtn">Log In</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Check if PHP set the success message
            <?php if ($success_message): ?>
                // If success, show the modal
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
            <?php endif; ?>

            // Handle the "Log In" button click
            const goLoginBtn = document.getElementById('goLoginBtn');
            if (goLoginBtn) {
                goLoginBtn.addEventListener('click', () => {
                    window.location.href = 'login.php';
                });
            }
        });
    </script>
</body>

</html>