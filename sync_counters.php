#!/usr/bin/env php
<?php
/**
 * Synchronize reader_flux_user_stats counters from reader_unread_cache
 * Run this periodically to fix any counter drift
 */

include(__DIR__ . '/conf.php');
$mysqli = $_SESSION['mysqli'];

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              SYNCHRONIZE UNREAD COUNTERS                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Get current state
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM reader_flux_user_stats");
$before = $result->fetch_assoc()['cnt'];
echo "Current entries in reader_flux_user_stats: " . number_format($before) . "\n";

$result = $mysqli->query("SELECT SUM(unread_count) as total FROM reader_flux_user_stats");
$totalBefore = $result->fetch_assoc()['total'];
echo "Total unread count (before): " . number_format($totalBefore) . "\n\n";

// Recalculate from cache
echo "Recalculating counters from reader_unread_cache...\n";
$start = microtime(true);

$mysqli->query("
    INSERT INTO reader_flux_user_stats (id_user, id_flux, unread_count)
    SELECT
        C.id_user,
        C.id_flux,
        COUNT(*) as unread_count
    FROM reader_unread_cache C
    GROUP BY C.id_user, C.id_flux
    ON DUPLICATE KEY UPDATE unread_count = VALUES(unread_count)
");

$duration = microtime(true) - $start;
$affected = $mysqli->affected_rows;

echo sprintf("✓ Processed %s rows in %.2f seconds\n\n", number_format($affected), $duration);

// Get new state
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM reader_flux_user_stats");
$after = $result->fetch_assoc()['cnt'];

$result = $mysqli->query("SELECT SUM(unread_count) as total FROM reader_flux_user_stats");
$totalAfter = $result->fetch_assoc()['total'];
echo "Total entries: " . number_format($after) . "\n";
echo "Total unread count (after): " . number_format($totalAfter) . "\n";
echo "Difference: " . number_format($totalAfter - $totalBefore) . "\n\n";

// Clean up entries with 0 count
echo "Cleaning up zero-count entries...\n";
$mysqli->query("DELETE FROM reader_flux_user_stats WHERE unread_count = 0");
$deleted = $mysqli->affected_rows;
echo "✓ Deleted " . number_format($deleted) . " entries\n\n";

echo str_repeat("═", 64) . "\n";
echo "✓ SYNCHRONIZATION COMPLETE\n";
echo str_repeat("═", 64) . "\n";
?>
