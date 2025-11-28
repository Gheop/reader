#!/usr/bin/env php
<?php
/**
 * Automatic Archive Script
 * Archives articles older than X days to reader_item_archive
 * Run this periodically (e.g., weekly via cron)
 */

include(__DIR__ . '/conf.php');
$mysqli = $_SESSION['mysqli'];

// Configuration
$archiveDays = 30; // Archive articles older than 30 days

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              AUTO ARCHIVE OLD ARTICLES                         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Archive threshold: Articles older than $archiveDays days\n\n";

// Count articles to archive
$result = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM reader_item
    WHERE pubdate < DATE_SUB(NOW(), INTERVAL $archiveDays DAY)
");
$toArchive = $result->fetch_assoc()['cnt'];

if ($toArchive == 0) {
    echo "✓ No articles to archive.\n";
    exit(0);
}

echo "Found " . number_format($toArchive) . " articles to archive\n\n";

// Start transaction
$mysqli->begin_transaction();

try {
    $start = microtime(true);

    // Step 1: Clean reader_unread_cache BEFORE archiving
    echo "Cleaning reader_unread_cache for old articles...\n";
    $mysqli->query("
        DELETE C FROM reader_unread_cache C
        INNER JOIN reader_item I ON I.id = C.id_item
        WHERE I.pubdate < DATE_SUB(NOW(), INTERVAL $archiveDays DAY)
    ");
    $cacheDeleted = $mysqli->affected_rows;
    echo "✓ Removed $cacheDeleted entries from unread cache\n";

    // Step 2: Update counters after cache cleanup
    echo "Updating counters...\n";

    // First, delete all existing counters to force a clean rebuild
    $mysqli->query("DELETE FROM reader_flux_user_stats");

    // Then rebuild from cache
    $mysqli->query("
        INSERT INTO reader_flux_user_stats (id_user, id_flux, unread_count)
        SELECT
            C.id_user,
            C.id_flux,
            COUNT(*) as unread_count
        FROM reader_unread_cache C
        GROUP BY C.id_user, C.id_flux
    ");

    $rebuiltCounters = $mysqli->affected_rows;
    echo "✓ Counters rebuilt ($rebuiltCounters feeds)\n";

    // Step 3: Copy to archive
    echo "Copying to reader_item_archive...\n";
    $mysqli->query("
        INSERT IGNORE INTO reader_item_archive
        SELECT * FROM reader_item
        WHERE pubdate < DATE_SUB(NOW(), INTERVAL $archiveDays DAY)
    ");
    $archived = $mysqli->affected_rows;
    echo "✓ Archived $archived articles\n";

    // Step 4: Delete from main table
    echo "Removing from reader_item...\n";
    $mysqli->query("
        DELETE FROM reader_item
        WHERE pubdate < DATE_SUB(NOW(), INTERVAL $archiveDays DAY)
    ");
    $deleted = $mysqli->affected_rows;
    echo "✓ Deleted $deleted articles\n";

    $duration = microtime(true) - $start;

    $mysqli->commit();

    echo "\n";
    echo sprintf("✓ Archive complete in %.2f seconds\n", $duration);
    echo "\n";

    // Show new sizes
    $result = $mysqli->query("SELECT COUNT(*) as cnt FROM reader_item");
    $mainCount = $result->fetch_assoc()['cnt'];

    $result = $mysqli->query("SELECT COUNT(*) as cnt FROM reader_item_archive");
    $archiveCount = $result->fetch_assoc()['cnt'];

    echo "Current state:\n";
    echo "  - reader_item: " . number_format($mainCount) . " articles\n";
    echo "  - reader_item_archive: " . number_format($archiveCount) . " articles\n";

    echo "\n";
    echo str_repeat("═", 64) . "\n";
    echo "✓ ARCHIVING COMPLETE\n";
    echo str_repeat("═", 64) . "\n";

} catch (Exception $e) {
    $mysqli->rollback();
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
