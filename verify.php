<?php
// Set PHP's timezone
date_default_timezone_set('Asia/Manila');

session_start();
require_once 'includes/db.php'; // This now sets the DB connection timezone too

$error_message = '';
$success_message = '';
$user_email = $_GET['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['verification_code'] ?? '';
    $email = $_POST['email'] ?? '';

    if (empty($code) || empty($email)) {
        $error_message = "Please enter the 6-digit code.";
    } else {
        // This query now works correctly because NOW() uses the connection timezone
        $stmt = $conn->prepare("SELECT id, is_verified FROM users WHERE email = ? AND verification_code = ? AND code_expires_at > NOW()");

        if ($stmt === false) {
            $error_message = "Database error. Please try again later.";
            error_log("Prepare failed (stmt): " . $conn->error);
        } else {
            $stmt->bind_param("ss", $email, $code);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                // SUCCESS! Code is correct and valid.
                $user_data = $result->fetch_assoc();
                $stmt->close();

                if ($user_data['is_verified'] == 1) {
                    $error_message = "This account is already verified. You can log in.";
                } else {
                    $stmt_update = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL, code_expires_at = NULL WHERE email = ?");
                    if ($stmt_update) { // Check if prepare succeeded
                        $stmt_update->bind_param("s", $email);
                        $stmt_update->execute();
                        $stmt_update->close();
                        $success_message = "Verification Successful! You can now log in.";
                    } else {
                        $error_message = "Database error updating account.";
                        error_log("Prepare failed (stmt_update): " . $conn->error);
                    }
                }
            } else {
                // FAILED! Code is wrong or expired.
                $stmt->close();

                $stmt_check_user = $conn->prepare("SELECT id FROM users WHERE email = ?");

                if ($stmt_check_user === false) {
                    $error_message = "Database error checking user.";
                    error_log("Prepare failed (stmt_check_user): " . $conn->error);
                } else {
                    $stmt_check_user->bind_param("s", $email);
                    $stmt_check_user->execute();
                    $check_result = $stmt_check_user->get_result(); // Store result before closing

                    if ($check_result->num_rows > 0) {
                        $error_message = "Invalid or expired verification code. Please try again or resend.";
                    } else {
                        $error_message = "Invalid email address.";
                    }
                    $stmt_check_user->close();
                }
            }
        }
    }
} // End of POST request handling

// Close connection if it's still open (moved outside POST block)
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
    </style>
</head>

<body class="verify-page">
    <main class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="row justify-content-center">
            <div class="col-md-6">

                <div class="verify-container">

                    <header>
                        <h1 class="text-center">Verify Your Account</h1>
                        <p class="text-center text-muted">
                            A 6-digit code was sent to <br><strong><?= htmlspecialchars($user_email) ?></strong>
                        </p>
                    </header>

                    <form id="verifyForm" method="POST" action="verify.php?email=<?= urlencode($user_email) ?>">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($user_email) ?>">

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger text-center"><?= $error_message ?></div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="verification_code" class="form-label">Verification Code</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" class="form-control" id="verification_code" name="verification_code" required maxlength="6"
                                style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5rem;" autofocus>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Verify
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <p>Didn't get a code? <a href="#" id="resendLink" data-email="<?= htmlspecialchars($user_email) ?>">Resend code</a></p>
                        <p id="resendFeedback"></p>
                    </div>
                </div>

            </div>
        </div>
    </main>

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

            // --- Resend Code Logic ---
            const resendLink = document.getElementById('resendLink');
            const resendFeedback = document.getElementById('resendFeedback');
            let cooldownTimer = null;
            let cooldownSeconds = 60;

            if (resendLink) {
                resendLink.addEventListener('click', async (e) => {
                    e.preventDefault();

                    if (cooldownTimer) return; // Do nothing if in cooldown

                    const email = resendLink.dataset.email;
                    resendFeedback.textContent = 'Sending...';
                    resendFeedback.className = 'text-muted';
                    resendLink.classList.add('disabled');

                    try {
                        // This path MUST be correct
                        const response = await fetch('ajax/resend_code.php', {
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

                            // Start cooldown timer
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
                        console.error('Resend error:', error);
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