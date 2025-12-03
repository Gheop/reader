<?php
/**
 * User Registration Page
 * Allows new users to create an account
 */

require_once(__DIR__ . '/conf.php');

$error = '';
$success = '';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pseudo = trim($_POST['pseudo'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $email = trim($_POST['email'] ?? '');

    // Validate input
    if (empty($pseudo) || empty($password)) {
        $error = 'Username and password are required';
    } elseif (strlen($pseudo) < 3) {
        $error = 'Username must be at least 3 characters long';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $pseudo)) {
        $error = 'Username can only contain letters, numbers, and underscores';
    } else {
        // Check if username already exists
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE pseudo = ?");
        $stmt->bind_param("s", $pseudo);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Username already exists';
        } else {
            // Hash password
            $pwd_hash = password_hash($password, PASSWORD_DEFAULT);

            // Create user
            $stmt = $mysqli->prepare("INSERT INTO users (pseudo, pwd, mail, date_create) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("sss", $pseudo, $pwd_hash, $email);

            if ($stmt->execute()) {
                $user_id = $mysqli->insert_id;

                // Set session
                $_SESSION['pseudo'] = $pseudo;
                $_SESSION['user_id'] = $user_id;

                // Set persistent cookie
                setcookie("session", $pseudo . "|" . $pwd_hash, [
                    'expires' => time() + 26000000,
                    'path' => '/',
                    'domain' => '',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);

                // Show success page directly (no HTTP redirect, so cookies are sent with the response)
                include(__DIR__ . '/login_success.php');
                exit;
            } else {
                $error = 'Failed to create account: ' . $mysqli->error;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Gheop Reader</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
            padding: 40px;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }

        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
        }

        button:active {
            transform: translateY(0);
        }

        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: #efe;
            color: #3c3;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .note {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Account</h1>
        <p class="subtitle">Join Gheop Reader</p>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="pseudo">Username</label>
                <input type="text" id="pseudo" name="pseudo" required 
                       value="<?= htmlspecialchars($_POST['pseudo'] ?? '') ?>"
                       pattern="[a-zA-Z0-9_]+" 
                       title="Only letters, numbers, and underscores allowed">
                <div class="note">At least 3 characters, letters/numbers/underscores only</div>
            </div>

            <div class="form-group">
                <label for="email">Email (optional)</label>
                <input type="email" id="email" name="email" 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <div class="note">Optional, for password recovery</div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="6">
                <div class="note">At least 6 characters</div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="6">
            </div>

            <button type="submit">Create Account</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
    </div>
</body>
</html>
