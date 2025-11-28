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
    'grant_type' => 'authorization_code',
    'redirect_uri' => $config['redirect_uri']
];

// For PKCE (Twitter), use code_verifier; credentials go in Basic Auth header
if (!empty($config['use_pkce']) && isset($_SESSION['oauth_code_verifier'])) {
    $token_data['code_verifier'] = $_SESSION['oauth_code_verifier'];
    // Twitter still needs client_id in body even with Basic Auth
    $token_data['client_id'] = $config['client_id'];
} else {
    // For non-PKCE providers, include credentials in body
    $token_data['client_id'] = $config['client_id'];
    $token_data['client_secret'] = $config['client_secret'];
}

$ch = curl_init($config['token_url']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Twitter requires Basic Auth with client credentials in header
$headers = [
    'Accept: application/json',
    'User-Agent: Gheop-Reader/1.0'
];
if (!empty($config['use_pkce'])) {
    // Use HTTP Basic Auth for PKCE providers (Twitter)
    curl_setopt($ch, CURLOPT_USERPWD, $config['client_id'] . ':' . $config['client_secret']);
    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
    'Accept: application/json',
    'User-Agent: Gheop-Reader/1.0'
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

        // If email is not public, fetch from /user/emails endpoint
        if (empty($email)) {
            $ch = curl_init('https://api.github.com/user/emails');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token,
                'Accept: application/json',
                'User-Agent: Gheop-Reader/1.0'
            ]);
            $emails_response = curl_exec($ch);
            curl_close($ch);

            $emails = json_decode($emails_response, true);
            if (is_array($emails)) {
                // Find primary email
                foreach ($emails as $email_obj) {
                    if (isset($email_obj['primary']) && $email_obj['primary']) {
                        $email = $email_obj['email'];
                        break;
                    }
                }
                // If no primary found, use first email
                if (empty($email) && !empty($emails[0]['email'])) {
                    $email = $emails[0]['email'];
                }
            }
        }
        break;
    case 'twitter':
        // Twitter API v2 returns data in a "data" object
        $data = $userinfo['data'] ?? $userinfo;
        $provider_user_id = $data['id'] ?? '';
        $email = $data['email'] ?? '';
        $name = $data['name'] ?? $data['username'] ?? '';
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
    // New OAuth account - create a new user
    // Generate pseudo from name (or username for Twitter)
    $pseudo = strtolower(str_replace([' ', '@', '.', '-'], ['_', '_', '_', '_'], $name ?: 'user'));
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
}

$stmt->close();
