<?php
include('/www/conf.php');

header('Content-Type: text/plain');

echo "=== Session Debug ===\n\n";
echo "Session status: " . session_status() . " (2 = active)\n";
echo "Session ID: " . session_id() . "\n\n";

echo "Session data:\n";
print_r($_SESSION);

if (isset($_SESSION['user_id'])) {
    echo "\n✓ User authenticated as user_id=" . $_SESSION['user_id'];
} else {
    echo "\n✗ No authentication - user_id not in session";
}
