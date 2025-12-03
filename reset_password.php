<?php
/**
 * Password Reset Tool
 * One-time use script to reset password for accounts with truncated hashes
 * DELETE THIS FILE after use for security
 */

include(__DIR__ . '/conf.php');

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pseudo = $_POST['pseudo'] ?? '';
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($pseudo)) {
        $error = 'Username is required';
    } elseif (empty($new_password)) {
        $error = 'Password is required';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Check if user exists
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE pseudo = ?");
        $stmt->bind_param("s", $pseudo);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Update password
            $pwd_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $mysqli->prepare("UPDATE users SET pwd = ? WHERE id = ?");
            $update_stmt->bind_param("si", $pwd_hash, $row['id']);

            if ($update_stmt->execute()) {
                $success = true;
            } else {
                $error = 'Failed to update password';
            }
            $update_stmt->close();
        } else {
            $error = 'User not found';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }

        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: #d4edda;
            border: 1px solid #28a745;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .error {
            background: #f8d7da;
            border: 1px solid #dc3545;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
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
            border-radius: 5px;
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

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reset Password</h1>

        <div class="warning">
            <strong>⚠️ Security Notice:</strong> Delete this file after use!
        </div>

        <?php if ($success): ?>
            <div class="success">
                <strong>✓ Success!</strong> Your password has been reset.
                <br><br>
                <a href="login.php">Go to login page</a>
                <br><br>
                <strong>Important:</strong> Delete reset_password.php now for security.
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="pseudo">Username:</label>
                    <input type="text" id="pseudo" name="pseudo" required
                           value="<?= htmlspecialchars($_POST['pseudo'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="password">New Password:</label>
                    <input type="password" id="password" name="password" required
                           minlength="6" placeholder="At least 6 characters">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit">Reset Password</button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="/">← Back to home</a>
        </div>
    </div>
</body>
</html>
