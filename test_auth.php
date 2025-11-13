<?php
include('/www/conf.php');

header('Content-Type: text/plain');

echo "=== Authentication Debug ===\n\n";

echo "1. Cookie 'session': ";
if (isset($_COOKIE['session'])) {
    $auth = explode("|", $_COOKIE['session']);
    echo "EXISTS\n";
    echo "   Pseudo from cookie: " . $auth[0] . "\n";

    // Check in database
    $req = $mysqli->query("SELECT id, pseudo, pwd FROM users WHERE pseudo='" . $mysqli->real_escape_string($auth[0]) . "'");
    if ($req && $d = $req->fetch_array()) {
        echo "   User found in DB: " . $d['pseudo'] . " (ID: " . $d['id'] . ")\n";

        if ($d['pwd'] == $auth[1]) {
            echo "   ✓ Password matches - authentication SHOULD work\n";
            echo "\n2. But $_SESSION['user_id'] is: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "\n";
            echo "\nThis means the authentication code in index.php hasn't run yet.\n";
            echo "The cookie exists and is valid, but the session hasn't been populated.\n";
        } else {
            echo "   ✗ Password mismatch - cookie is invalid\n";
        }
    } else {
        echo "   ✗ User not found in database\n";
    }
} else {
    echo "NOT SET\n";
    echo "   You need to login first\n";
}

echo "\n3. Session data:\n";
print_r($_SESSION);
