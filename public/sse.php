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

include(__DIR__ . '/../config/conf.php');
include(__DIR__ . '/../config/auth.php');

// Security: Validate user authentication
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    // Log debug info
    error_log('SSE auth failed - Session status: ' . session_status() . ', Session ID: ' . session_id() . ', Has user_id: ' . (isset($_SESSION['user_id']) ? 'yes' : 'no'));

    echo "event: error\n";
    echo "data: {\"error\":\"Unauthorized\"}\n\n";
    flush();
    exit;
}

$userId = (int)$_SESSION['user_id'];
global $mysqli;

// IMPORTANT: Close session writing to prevent blocking other requests
// SSE keeps connection open, but we don't need to write to session
session_write_close();

// Send initial connection success message
echo "event: connected\n";
echo "data: {\"status\":\"ok\",\"timestamp\":" . time() . "}\n\n";
flush();

// Get initial state
$lastCheck = time();
$lastHash = '';

// Function to get current state hash - uses reader_flux_user_stats for accurate counts
function getCurrentHash($mysqli, $userId) {
    $stmt = $mysqli->prepare("
        SELECT
            COALESCE(SUM(S.unread_count), 0) as total,
            COALESCE(MAX(UNIX_TIMESTAMP(I.pubdate)), 0) as last_item,
            COALESCE(MAX(UNIX_TIMESTAMP(R.date)), 0) as last_read
        FROM reader_flux_user_stats S
        LEFT JOIN reader_item I ON I.id = (SELECT MAX(id) FROM reader_item)
        LEFT JOIN reader_user_item R ON R.id_user = ? AND R.id_item = (SELECT MAX(id_item) FROM reader_user_item WHERE id_user = ?)
        WHERE S.id_user = ?
    ");

    $stmt->bind_param("iii", $userId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        $data = $result->fetch_assoc();
        $stmt->close();
        // Hash includes: total unread count + last new article + last read action
        return md5($data['total'] . '_' . $data['last_item'] . '_' . $data['last_read']);
    }
    return '';
}

// Initial hash
$lastHash = getCurrentHash($mysqli, $userId);

// Keep connection alive and check for changes every 2 seconds
$maxDuration = 300; // 5 minutes max connection time
$startTime = time();

while (time() - $startTime < $maxDuration) {
    // Check if connection is still alive
    if (connection_aborted()) {
        break;
    }

    // Check for changes
    $currentHash = getCurrentHash($mysqli, $userId);

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
