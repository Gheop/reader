#!/usr/bin/env php
<?php
/**
 * Rebuild reader_unread_cache from reader_item
 * For all articles that are not marked as read
 */

include(__DIR__ . '/../config/conf.php');
$mysqli = $mysqli;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              REBUILD UNREAD CACHE                              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Get current cache size
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM reader_unread_cache");
$before = $result->fetch_assoc()['cnt'];
echo "Current cache entries: " . number_format($before) . "\n\n";

// Clear existing cache (will rebuild completely)
echo "Clearing existing cache...\n";
$mysqli->query("TRUNCATE TABLE reader_unread_cache");
echo "✓ Cache cleared\n\n";

// Rebuild cache from reader_item
echo "Rebuilding cache from reader_item...\n";
echo "(This may take a while...)\n\n";

$start = microtime(true);

// Insert all unread articles into cache
$mysqli->query("
    INSERT INTO reader_unread_cache (id_user, id_flux, id_item, pubdate)
    SELECT
        UF.id_user,
        I.id_flux,
        I.id,
        I.pubdate
    FROM reader_item I
    INNER JOIN reader_user_flux UF ON UF.id_flux = I.id_flux
    LEFT JOIN reader_user_item UI ON UI.id_item = I.id AND UI.id_user = UF.id_user
    WHERE UI.id IS NULL  -- Not read
");

$duration = microtime(true) - $start;
$affected = $mysqli->affected_rows;

echo sprintf("✓ Inserted %s entries in %.2f seconds\n\n", number_format($affected), $duration);

// Get new cache size
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM reader_unread_cache");
$after = $result->fetch_assoc()['cnt'];

// Verify integrity
echo "Verification:\n";
echo "  - Before: " . number_format($before) . " entries\n";
echo "  - After:  " . number_format($after) . " entries\n";
echo "  - Change: " . number_format($after - $before) . "\n\n";

// Show stats by user
$result = $mysqli->query("
    SELECT
        id_user,
        COUNT(*) as unread_count
    FROM reader_unread_cache
    GROUP BY id_user
    ORDER BY id_user
");

echo "Unread articles by user:\n";
while($row = $result->fetch_assoc()) {
    echo "  User " . $row['id_user'] . ": " . number_format($row['unread_count']) . " unread\n";
}

echo "\n";
echo str_repeat("═", 64) . "\n";
echo "Synchronizing counters...\n";
echo str_repeat("═", 64) . "\n\n";

// Automatically sync counters after cache rebuild
$mysqli->query("DELETE FROM reader_flux_user_stats");
$mysqli->query("
    INSERT INTO reader_flux_user_stats (id_user, id_flux, unread_count)
    SELECT
        C.id_user,
        C.id_flux,
        COUNT(*) as unread_count
    FROM reader_unread_cache C
    GROUP BY C.id_user, C.id_flux
");
$synced = $mysqli->affected_rows;
echo "✓ Counters synchronized ($synced feeds)\n\n";

echo str_repeat("═", 64) . "\n";
echo "✓ CACHE REBUILD & SYNC COMPLETE\n";
echo str_repeat("═", 64) . "\n\n";
?>
