#!/usr/bin/env php
<?php
/**
 * RSS Feed Update Daemon
 * Runs continuously and updates feeds every 10 minutes
 *
 * Usage:
 *   Start:   php update_daemon.php
 *   Stop:    kill $(cat /tmp/reader_update_daemon.pid)
 *   Restart: kill $(cat /tmp/reader_update_daemon.pid) && php update_daemon.php &
 *
 * Systemd service:
 *   sudo systemctl start reader-update-daemon
 *   sudo systemctl enable reader-update-daemon
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

// Configuration
define('UPDATE_INTERVAL', 600); // 10 minutes in seconds
define('PID_FILE', '/tmp/reader_update_daemon.pid');
define('LOG_FILE', '/var/log/reader_update_daemon.log');
define('MAX_RUNTIME', 86400); // 24 hours, then restart

// Check if already running
if (file_exists(PID_FILE)) {
    $pid = (int)file_get_contents(PID_FILE);
    if (posix_kill($pid, 0)) {
        die("Daemon already running (PID: $pid)\n");
    } else {
        // Stale PID file, remove it
        unlink(PID_FILE);
    }
}

// Write PID file
file_put_contents(PID_FILE, getmypid());

// Cleanup on exit
register_shutdown_function(function() {
    if (file_exists(PID_FILE)) {
        unlink(PID_FILE);
    }
    logMessage("Daemon stopped");
});

// Handle signals
pcntl_signal(SIGTERM, function() {
    logMessage("Received SIGTERM, shutting down...");
    exit(0);
});
pcntl_signal(SIGINT, function() {
    logMessage("Received SIGINT, shutting down...");
    exit(0);
});

/**
 * Log message to file and stdout
 */
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    echo $logLine;
    @file_put_contents(LOG_FILE, $logLine, FILE_APPEND);
}

/**
 * Run update script
 */
function runUpdate() {
    $startTime = microtime(true);
    logMessage("Starting feed update...");

    // Run update script and capture output
    $output = [];
    $returnCode = 0;
    exec('php ' . escapeshellarg(__DIR__ . '/up_parallel.php') . ' 2>&1', $output, $returnCode);

    $duration = round(microtime(true) - $startTime, 2);

    if ($returnCode === 0) {
        // Extract statistics from output
        $stats = extractStats($output);
        logMessage("Update completed in {$duration}s - {$stats['processed']} feeds, {$stats['new_articles']} new articles, {$stats['errors']} errors");
    } else {
        logMessage("Update failed with exit code $returnCode after {$duration}s");
        logMessage("Last 5 lines: " . implode(' | ', array_slice($output, -5)));
    }
}

/**
 * Extract statistics from update output
 */
function extractStats($output) {
    $stats = [
        'processed' => 0,
        'new_articles' => 0,
        'errors' => 0
    ];

    foreach ($output as $line) {
        if (preg_match('/Processed:\s+(\d+)/', $line, $m)) {
            $stats['processed'] = (int)$m[1];
        }
        if (preg_match('/New articles:\s+(\d+)/', $line, $m)) {
            $stats['new_articles'] = (int)$m[1];
        }
        if (preg_match('/Errors:\s+(\d+)/', $line, $m)) {
            $stats['errors'] = (int)$m[1];
        }
    }

    return $stats;
}

// Main daemon loop
logMessage("Feed update daemon started (PID: " . getmypid() . ")");
logMessage("Update interval: " . UPDATE_INTERVAL . " seconds (" . (UPDATE_INTERVAL / 60) . " minutes)");

$startupTime = time();
$lastUpdate = 0;

while (true) {
    pcntl_signal_dispatch(); // Handle signals

    $now = time();

    // Check if it's time to update
    if (($now - $lastUpdate) >= UPDATE_INTERVAL) {
        runUpdate();
        $lastUpdate = $now;
    }

    // Auto-restart after MAX_RUNTIME to prevent memory leaks
    if (($now - $startupTime) >= MAX_RUNTIME) {
        logMessage("Max runtime reached, restarting daemon...");
        exit(0);
    }

    // Sleep for 60 seconds between checks
    sleep(60);
}
?>
