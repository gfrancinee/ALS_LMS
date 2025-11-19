<?php
// Set PHP's timezone
date_default_timezone_set('Asia/Manila');

session_start();
require_once 'includes/db.php';

$error_message = '';
$success_message = '';
$user_email = $_GET['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['verification_code'] ?? '';
    $email = $_POST['email'] ?? '';

    if (empty($code) || empty($email)) {
        $error_message = "Please enter the 6-digit code.";
    } else {
        // --- UPDATE 1: Also fetch the 'role' to give the right success message ---
        $stmt = $conn->prepare("SELECT id, is_verified, role FROM users WHERE email = ? AND verification_code = ? AND code_expires_at > NOW()");

        if ($stmt === false) {
            $error_message = "Database error. Please try again later.";
            error_log("Prepare failed (stmt): " . $conn->error);
        } else {
            $stmt->bind_param("ss", $email, $code);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                // SUCCESS! Code is correct.
                $user_data = $result->fetch_assoc();
                $stmt->close();

                if ($user_data['is_verified'] == 1) {
                    $error_message = "This account is already verified. You can log in.";
                } else {
                    $stmt_update = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL, code_expires_at = NULL WHERE email = ?");
                    if ($stmt_update) {
                        $stmt_update->bind_param("s", $email);
                        $stmt_update->execute();
                        $stmt_update->close();

                        // --- UPDATE 2: Custom Message based on Role ---
                        if ($user_data['role'] === 'student') {
                            // Student: Email is good, but wait for Admin
                            $success_message = "Email Verified! Your account is now pending for Admin Approval (LRN Check).";
                        } else {
                            // Teacher: Good to go
                            $success_message = "Verification Successful! You can now log in.";
                        }
                    } else {
                        $error_message = "Database error updating account.";
                    }
                }
            } else {
                // FAILED!
                $stmt->close();
                $error_message = "Invalid or expired verification code.";
            }
        }
    }
} // End of POST request handling

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Email Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/verify.css" />
    <style>
        #resendFeedback {
            min-height: 24px;
            font-size: 0.9em;
        }

        .cooldown {
            color: grey;
        }

        /* Custom CSS for the Modern Success Modal */
        .modal-content.shadow-lg {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
            /* Slightly stronger shadow */
        }

        .success-icon {
            width: 60px;
            /* Size of the circular icon */
            height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .success-icon i {
            font-size: 2.5rem !important;
            /* Adjust icon size */
        }

        /* Optional: Center button text */
        .modal-footer {
            justify-content: center;
        }

        /* For the new rounded-pill button if not already defined by Bootstrap */
        .btn.rounded-pill {
            border-radius: 50rem;
            /* Makes it truly pill-shaped */
        }

        body.verify-page {
            background-color: #f0f2f5;
            /* Soft gray background */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .verify-card {
            background: #ffffff;
            border-radius: 20px;
            /* Modern rounded corners */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            /* Soft, deep shadow */
            padding: 3rem 2.5rem;
            border: none;
            max-width: 450px;
            width: 100%;
        }

        .icon-circle {
            width: 80px;
            height: 80px;
            background-color: #e7f1ff;
            /* Light blue background */
            color: #0d6efd;
            /* Primary blue icon */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            font-size: 2.5rem;
        }

        .verification-input {
            text-align: center;
            font-size: 2rem;
            letter-spacing: 0.75rem;
            font-weight: 600;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            height: 60px;
            transition: all 0.3s ease;
        }

        .verification-input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
        }

        .btn-verify {
            padding: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: transform 0.2s;
        }

        .btn-verify:active {
            transform: scale(0.98);
        }

        #resendLink {
            text-decoration: none;
            font-weight: 500;
        }
    </style>
</head>

<body class="verify-page">
    <main class="container d-flex justify-content-center align-items-center min-vh-100">

        <div class="verify-card">
            <div class="icon-circle">
                <i class="bi bi-envelope-check-fill"></i>
            </div>

            <header class="mb-4">
                <h2 class="text-center fw-bold mb-2">Verify Your Email</h2>
                <p class="text-center text-muted small">
                    We've sent a 6-digit code to<br>
                    <span class="text-dark fw-semibold"><?= htmlspecialchars($user_email) ?></span>
                </p>
            </header>

            <form id="verifyForm" method="POST" action="verify.php?email=<?= urlencode($user_email) ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($user_email) ?>">

                <?php if ($error_message): ?>
                    <div class="alert alert-danger text-center border-0 shadow-sm rounded-3 small mb-4">
                        <i class="bi bi-exclamation-circle me-1"></i> <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <div class="mb-4">
                    <input type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        autocomplete="one-time-code"
                        class="form-control verification-input"
                        id="verification_code"
                        name="verification_code"
                        required
                        maxlength="6"
                        placeholder="000000"
                        autofocus>
                </div>

                <button type="submit" class="btn btn-primary w-100 rounded-pill btn-verify">
                    Verify Account
                </button>
            </form>

            <div class="text-center mt-4">
                <p class="small text-muted mb-1">Didn't receive the code?</p>
                <a href="#" id="resendLink" data-email="<?= htmlspecialchars($user_email) ?>">Resend Code</a>
                <p id="resendFeedback" class="mt-2 small"></p>
            </div>
        </div>

    </main>

    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body text-center p-5">
                    <div class="d-flex justify-content-center align-items-center mb-3">
                        <div class="success-icon p-2 rounded-circle bg-success text-white">
                            <i class="bi bi-check-circle-fill fs-2"></i>
                        </div>
                    </div>
                    <h4 class="modal-title mb-3" id="successModalLabel">Success!</h4>
                    <p class="mb-4">Email Verified! Your account is now pending Admin Approval (LRN Check).</p>
                    <button type="button" class="btn btn-success rounded-pill px-4 py-2" onclick="window.location.href='login.php'">
                        Go to Login
                    </button>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Show success modal if PHP set the message
            <?php if ($success_message): ?>
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
            <?php endif; ?>

            const goLoginBtn = document.getElementById('goLoginBtn');
            if (goLoginBtn) {
                goLoginBtn.addEventListener('click', () => {
                    window.location.href = 'login.php';
                });
            }

            // --- Resend Code Logic (Uses your existing ajax/resend_code.php) ---
            const resendLink = document.getElementById('resendLink');
            const resendFeedback = document.getElementById('resendFeedback');
            let cooldownTimer = null;
            let cooldownSeconds = 60;

            if (resendLink) {
                resendLink.addEventListener('click', async (e) => {
                    e.preventDefault();
                    if (cooldownTimer) return;

                    const email = resendLink.dataset.email;
                    resendFeedback.textContent = 'Sending...';
                    resendFeedback.className = 'text-muted';
                    resendLink.classList.add('disabled');

                    try {
                        const response = await fetch('ajax/resend_code.php', { // This file calls your existing PHP
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                email: email
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            resendFeedback.textContent = data.message;
                            resendFeedback.className = 'text-success';

                            // Cooldown Timer
                            let secondsLeft = cooldownSeconds;
                            resendLink.textContent = `Resend code (${secondsLeft}s)`;
                            cooldownTimer = setInterval(() => {
                                secondsLeft--;
                                if (secondsLeft > 0) {
                                    resendLink.textContent = `Resend code (${secondsLeft}s)`;
                                } else {
                                    clearInterval(cooldownTimer);
                                    cooldownTimer = null;
                                    resendLink.textContent = 'Resend code';
                                    resendLink.classList.remove('disabled');
                                    resendFeedback.textContent = '';
                                }
                            }, 1000);
                        } else {
                            resendFeedback.textContent = data.message || 'Failed to resend code.';
                            resendFeedback.className = 'text-danger';
                            resendLink.classList.remove('disabled');
                        }
                    } catch (error) {
                        resendFeedback.textContent = 'An error occurred. Please try again.';
                        resendFeedback.className = 'text-danger';
                        resendLink.classList.remove('disabled');
                    }
                });
            }
        });
    </script>
</body>

</html>