<?php
/**
 * OAuth2 Login Initiator
 * Redirects user to OAuth provider for authentication
 */

// Load OAuth configuration
$oauth_config = include(__DIR__ . '/oauth_config.php');

// Get provider from query parameter
$provider = $_GET['provider'] ?? '';

if (!isset($oauth_config[$provider])) {
    die('Invalid OAuth provider');
}

$config = $oauth_config[$provider];

// Check if client ID is configured
if (empty($config['client_id'])) {
    die('OAuth provider not configured. Please contact administrator.');
}

// Generate state parameter for CSRF protection
session_start();
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Build authorization URL
$params = [
    'client_id' => $config['client_id'],
    'redirect_uri' => $config['redirect_uri'],
    'response_type' => 'code',
    'scope' => implode(' ', $config['scopes']),
    'state' => $state
];

$auth_url = $config['auth_url'] . '?' . http_build_query($params);

// Redirect to OAuth provider
header('Location: ' . $auth_url);
exit;
