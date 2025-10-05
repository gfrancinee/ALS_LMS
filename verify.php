<?php
// FILE: verify.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';

$token = $_GET['token'] ?? null;
$message = '';
$is_success = false;

if (!$token) {
    $message = "Verification token is missing. Please check your link.";
} else {
    $stmt = $conn->prepare("SELECT id, is_verified FROM users WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if ($user['is_verified']) {
            $message = "This account has already been verified. You can now log in.";
            $is_success = true;
        } else {
            $update_stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
            $update_stmt->bind_param("i", $user['id']);
            if ($update_stmt->execute()) {
                $message = "Thank you! Your account has been successfully verified.";
                $is_success = true;
            } else {
                $message = "An error occurred while verifying your account. Please try again later.";
            }
            $update_stmt->close();
        }
    } else {
        $message = "This verification link is invalid or has expired.";
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Account Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 mt-5">
                <div class="card text-center">
                    <div class="card-header">
                        <h2>Account Verification</h2>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title <?= $is_success ? 'text-success' : 'text-danger'; ?>">
                            <?= htmlspecialchars($message) ?>
                        </h5>
                        <?php if ($is_success): ?>
                            <a href="login.php" class="btn btn-primary mt-3">Proceed to Login</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>