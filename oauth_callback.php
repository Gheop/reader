<?php
/**
 * OAuth2 Callback Handler
 * Handles OAuth2 callbacks from various providers
 */

session_start();

// Create database connection
$mysqli = new mysqli('localhost', 'gheop', 'REDACTED', 'gheop');
if ($mysqli->connect_error) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

// Load OAuth configuration
$oauth_config = include(__DIR__ . '/oauth_config.php');

// Get provider from query parameter
$provider = $_GET['provider'] ?? '';

if (!isset($oauth_config[$provider])) {
    die('Invalid OAuth provider');
}

$config = $oauth_config[$provider];

// Check for authorization code
if (!isset($_GET['code'])) {
    die('No authorization code received');
}

$code = $_GET['code'];

// Exchange authorization code for access token
$token_data = [
    'code' => $code,
    'client_id' => $config['client_id'],
    'client_secret' => $config['client_secret'],
    'redirect_uri' => $config['redirect_uri'],
    'grant_type' => 'authorization_code'
];

$ch = curl_init($config['token_url']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

$token_response = curl_exec($ch);
curl_close($ch);

$token_info = json_decode($token_response, true);

if (!isset($token_info['access_token'])) {
    error_log('OAuth token error: ' . $token_response);
    die('Failed to obtain access token');
}

$access_token = $token_info['access_token'];

// Fetch user information
$ch = curl_init($config['userinfo_url']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Accept: application/json'
]);

$userinfo_response = curl_exec($ch);
curl_close($ch);

$userinfo = json_decode($userinfo_response, true);

if (!$userinfo) {
    error_log('OAuth userinfo error: ' . $userinfo_response);
    die('Failed to fetch user information');
}

// Extract user data based on provider
$provider_user_id = '';
$email = '';
$name = '';

switch ($provider) {
    case 'google':
        $provider_user_id = $userinfo['id'] ?? '';
        $email = $userinfo['email'] ?? '';
        $name = $userinfo['name'] ?? '';
        break;
    case 'github':
        $provider_user_id = $userinfo['id'] ?? '';
        $email = $userinfo['email'] ?? '';
        $name = $userinfo['name'] ?? $userinfo['login'] ?? '';
        break;
    case 'microsoft':
        $provider_user_id = $userinfo['id'] ?? '';
        $email = $userinfo['mail'] ?? $userinfo['userPrincipalName'] ?? '';
        $name = $userinfo['displayName'] ?? '';
        break;
}

if (!$provider_user_id) {
    die('Failed to extract user ID from provider');
}

// Check if OAuth account already exists
$stmt = $mysqli->prepare("SELECT user_id FROM user_oauth WHERE provider = ? AND provider_user_id = ?");
$stmt->bind_param("ss", $provider, $provider_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Existing OAuth account - log in
    $user_id = $row['user_id'];

    // Fetch user details
    $stmt = $mysqli->prepare("SELECT id, pseudo, pwd FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();

    if ($user = $user_result->fetch_assoc()) {
        // Set session
        $_SESSION['pseudo'] = $user['pseudo'];
        $_SESSION['user_id'] = $user['id'];

        // Set persistent cookie with proper SameSite attribute
        setcookie("session", $user['pseudo'] . "|" . $user['pwd'], [
            'expires' => time() + 26000000,
            'path' => '/',
            'domain' => '.gheop.com',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'  // Lax allows cookie after OAuth redirect
        ]);

        // Redirect to reader
        header('Location: /');
        exit;
    }
} else {
    // New OAuth account - check if email already exists
    if ($email) {
        // For now, create a new user with a generated pseudo
        // In the future, you might want to ask the user or link to existing account

        // Generate pseudo from email or name
        $pseudo = strtolower(str_replace([' ', '@', '.'], ['_', '_', '_'], $name ?: $email));
        $pseudo = substr($pseudo, 0, 20); // Limit to 20 chars

        // Check if pseudo exists, add number if needed
        $original_pseudo = $pseudo;
        $counter = 1;
        while (true) {
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE pseudo = ?");
            $stmt->bind_param("s", $pseudo);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                break; // Pseudo is available
            }

            $pseudo = $original_pseudo . $counter;
            $counter++;
        }

        // Create new user
        $pwd_hash = bin2hex(random_bytes(20)); // Random password hash
        $stmt = $mysqli->prepare("INSERT INTO users (pseudo, pwd, mail, date_create) VALUES (?, ?, '', NOW())");
        $stmt->bind_param("ss", $pseudo, $pwd_hash);

        if ($stmt->execute()) {
            $user_id = $mysqli->insert_id;

            // Create OAuth link
            $stmt = $mysqli->prepare("INSERT INTO user_oauth (user_id, provider, provider_user_id, email, name) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $user_id, $provider, $provider_user_id, $email, $name);
            $stmt->execute();

            // Set session
            $_SESSION['pseudo'] = $pseudo;
            $_SESSION['user_id'] = $user_id;

            // Set persistent cookie with proper SameSite attribute
            setcookie("session", $pseudo . "|" . $pwd_hash, [
                'expires' => time() + 26000000,
                'path' => '/',
                'domain' => '.gheop.com',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'  // Lax allows cookie after OAuth redirect
            ]);

            // Redirect to reader
            header('Location: /');
            exit;
        } else {
            die('Failed to create user account');
        }
    } else {
        die('No email provided by OAuth provider');
    }
}

$stmt->close();
