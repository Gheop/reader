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

// Delete persistent session cookie - try all possible domains
// Delete cookie without domain (for local cookies)
setcookie("session", '', [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Delete cookie with .gheop.com domain (for OAuth cookies)
setcookie("session", '', time() - 3600, '/', '.gheop.com');

// Delete old cookies from previous system
setcookie("session", '', time() - 3600, '/', '.gheop.net');
setcookie("session", '', time() - 3600, '/', '.gheop.org');

// Destroy session
session_destroy();

// Redirect to logout success page (allows cookies to be cleared before final redirect)
header('Location: /logout_success.php');
exit;
