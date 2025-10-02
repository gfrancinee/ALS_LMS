<?php
session_start();

// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect the user to the login page (adjust the path if necessary)
header("Location: login.php");
exit;
