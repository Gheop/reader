<?php
/**
 * User Logout
 * Logs out the current user and clears session/cookies
 */

// Start session if not already started
if (session_status() != PHP_SESSION_ACTIVE) {
    session_start();
}

// Clear session variables
$_SESSION = [];

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Delete persistent session cookie
setcookie("session", '', [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Destroy session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
