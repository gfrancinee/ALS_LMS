<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout settings
$timeout = 1800;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=true");
    exit();
}
$_SESSION['last_activity'] = time(); // update activity timestamp

// Role-based access control
if (isset($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: login.php?unauthorized=true");
    exit();
}
