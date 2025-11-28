<?php
/**
 * Configuration file for Gheop Reader
 * Contains database connection and API keys
 *
 * IMPORTANT: Copy this file to conf.php and fill in your own values
 * Do NOT commit conf.php to version control!
 */

// Start session if not already started
if (session_status() != PHP_SESSION_ACTIVE) {
    // Server should keep session data for AT LEAST 1 hour
    ini_set('session.gc_maxlifetime', 3600);

    // Security: Set secure cookie parameters
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'domain' => '',
        'secure' => true,      // Only send over HTTPS
        'httponly' => true,    // Prevent JavaScript access (XSS protection)
        'samesite' => 'Lax'    // Allow cookies on redirects (OAuth, login)
    ]);

    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_db_name');

// Create database connection
global $mysqli;
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

// AI Configuration (DeepSeek) - Optional
define('AI_API_KEY', 'your_deepseek_api_key');
define('AI_API_URL', 'https://api.deepseek.com/chat/completions');
define('AI_MODEL', 'deepseek-chat');

// YouTube API Key for fetching video descriptions - Optional
define('YOUTUBE_API_KEY', 'your_youtube_api_key');
