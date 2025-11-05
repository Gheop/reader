<?php
/**
 * Server-Sent Events endpoint for real-time push notifications
 * Keeps connection open and sends events when data changes
 */

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Disable PHP output buffering
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
ob_implicit_flush(1);
while (ob_get_level() > 0) {
    ob_end_flush();
}

include('/www/conf.php');

// Security: Validate user authentication
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    echo "event: error\n";
    echo "data: {\"error\":\"Unauthorized\"}\n\n";
    flush();
    exit;
}

$userId = (int)$_SESSION['user_id'];
$mysqli = $_SESSION['mysqli'];

// IMPORTANT: Close session writing to prevent blocking other requests
// SSE keeps connection open, but we don't need to write to session
session_write_close();

// Send initial connection success message
echo "event: connected\n";
echo "data: {\"status\":\"ok\",\"timestamp\":" . time() . "}\n\n";
flush();

// Get initial state
$counterColumn = $userId == 1 ? 'unread_count_user_1' : 'unread_count_user_2';
$lastCheck = time();
$lastHash = '';

// Function to get current state hash
function getCurrentHash($mysqli, $userId, $counterColumn) {
    $result = $mysqli->query("
        SELECT SUM($counterColumn) as total,
               MAX(UNIX_TIMESTAMP(GREATEST(
                   COALESCE((SELECT MAX(pubdate) FROM reader_item), '1970-01-01'),
                   COALESCE((SELECT MAX(pubdate) FROM reader_unread_cache WHERE id_user = $userId), '1970-01-01')
               ))) as last_change
        FROM reader_flux
    ");

    if ($result) {
        $data = $result->fetch_assoc();
        return md5($data['total'] . '_' . $data['last_change']);
    }
    return '';
}

// Initial hash
$lastHash = getCurrentHash($mysqli, $userId, $counterColumn);

// Keep connection alive and check for changes every 2 seconds
$maxDuration = 300; // 5 minutes max connection time
$startTime = time();

while (time() - $startTime < $maxDuration) {
    // Check if connection is still alive
    if (connection_aborted()) {
        break;
    }

    // Check for changes
    $currentHash = getCurrentHash($mysqli, $userId, $counterColumn);

    if ($currentHash !== $lastHash && $currentHash !== '') {
        // Data has changed, notify client
        $lastHash = $currentHash;

        echo "event: update\n";
        echo "data: {\"timestamp\":" . time() . ",\"reason\":\"data_changed\"}\n\n";
        flush();
    }

    // Send heartbeat every 30 seconds
    if (time() - $lastCheck >= 30) {
        $lastCheck = time();
        echo "event: heartbeat\n";
        echo "data: {\"timestamp\":" . time() . "}\n\n";
        flush();
    }

    // Sleep for 2 seconds before next check
    sleep(2);
}

// Connection timeout, ask client to reconnect
echo "event: timeout\n";
echo "data: {\"timestamp\":" . time() . "}\n\n";
flush();
?>
