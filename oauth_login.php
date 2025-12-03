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

// Add PKCE for providers that require it (Twitter)
if (!empty($config['use_pkce'])) {
    // Generate code verifier (random 43-128 character string)
    $code_verifier = bin2hex(random_bytes(32)); // 64 characters
    $_SESSION['oauth_code_verifier'] = $code_verifier;

    // Generate code challenge (base64url encoded SHA256 hash of verifier)
    $code_challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');

    $params['code_challenge'] = $code_challenge;
    $params['code_challenge_method'] = 'S256';
}

$auth_url = $config['auth_url'] . '?' . http_build_query($params);

// Redirect to OAuth provider
header('Location: ' . $auth_url);
exit;
