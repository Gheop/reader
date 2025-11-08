<?php
/**
 * Authentication handler
 * Checks for session cookie and populates $_SESSION with user data
 * Should be included after conf.php
 */

// If user is not already authenticated in session, check cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['session'])) {
    $auth = explode("|", $_COOKIE['session']);

    if (count($auth) === 2 && !empty($auth[0])) {
        // Use prepared statement to prevent SQL injection
        $stmt = $mysqli->prepare("SELECT id, pseudo, pwd FROM users WHERE pseudo = ?");
        $stmt->bind_param("s", $auth[0]);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $d = $result->fetch_assoc()) {
            // Verify password hash from cookie matches database
            if ($d['pwd'] === $auth[1]) {
                // Authentication successful - populate session
                $_SESSION['pseudo'] = $d['pseudo'];
                $_SESSION['user_id'] = $d['id'];

                // Refresh cookie to extend expiration (300 days)
                setrawcookie("session", $d['pseudo'] . "|" . $d['pwd'], time() + 26000000, '/', '.gheop.com');
            }
        }

        $stmt->close();
    }
}
