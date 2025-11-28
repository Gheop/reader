#!/usr/bin/env php
<?php
/**
 * STEP 4: Denormalize reader_unread_cache
 * Add title, author, description, link to eliminate JOIN with reader_item
 */

include(__DIR__ . '/conf.php');
$mysqli = $mysqli;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║       STEP 4: DENORMALIZE CACHE (Eliminate JOIN)              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Step 1: Check current structure
echo "[1/5] Analyzing current structure...\n";
$result = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM reader_unread_cache
");
$count = $result->fetch_assoc()['cnt'];
echo "  - Current rows in cache: " . number_format($count) . "\n";

$result = $mysqli->query("
    SELECT
        ROUND(DATA_LENGTH / 1024 / 1024, 2) as data_mb,
        ROUND(INDEX_LENGTH / 1024 / 1024, 2) as index_mb
    FROM information_schema.TABLES
    WHERE TABLE_NAME = 'reader_unread_cache' AND TABLE_SCHEMA = 'gheop'
");
$size = $result->fetch_assoc();
echo "  - Current size: {$size['data_mb']} MB data + {$size['index_mb']} MB index\n\n";

// Step 2: Add new columns
echo "[2/5] Adding denormalized columns...\n";
echo "  (This may take a moment for " . number_format($count) . " rows...)\n";

$start = microtime(true);
$mysqli->query("
    ALTER TABLE reader_unread_cache
    ADD COLUMN title TEXT DEFAULT NULL AFTER id_item,
    ADD COLUMN author VARCHAR(255) DEFAULT NULL AFTER title,
    ADD COLUMN description TEXT DEFAULT NULL AFTER author,
    ADD COLUMN link VARCHAR(2048) DEFAULT NULL AFTER description
");
$duration = microtime(true) - $start;

if ($mysqli->error) {
    echo "  ✗ Error: {$mysqli->error}\n";
    exit(1);
}

echo sprintf("  ✓ Columns added in %.2f seconds\n\n", $duration);

// Step 3: Populate the new columns from reader_item
echo "[3/5] Populating columns from reader_item...\n";
echo "  (This may take a while for " . number_format($count) . " rows...)\n";

$start = microtime(true);
$mysqli->query("
    UPDATE reader_unread_cache C
    INNER JOIN reader_item I ON C.id_item = I.id
    SET
        C.title = I.title,
        C.author = I.author,
        C.description = I.description,
        C.link = I.link
");
$duration = microtime(true) - $start;
$affected = $mysqli->affected_rows;

if ($mysqli->error) {
    echo "  ✗ Error: {$mysqli->error}\n";
    exit(1);
}

echo sprintf("  ✓ Updated %s rows in %.2f seconds\n\n", number_format($affected), $duration);

// Step 4: Verify data integrity
echo "[4/5] Verifying data integrity...\n";

$result = $mysqli->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN title IS NOT NULL THEN 1 ELSE 0 END) as with_title,
        SUM(CASE WHEN link IS NOT NULL THEN 1 ELSE 0 END) as with_link
    FROM reader_unread_cache
");
$stats = $result->fetch_assoc();

echo "  - Total rows: " . number_format($stats['total']) . "\n";
echo "  - Rows with title: " . number_format($stats['with_title']) . " (" . round($stats['with_title']/$stats['total']*100, 1) . "%)\n";
echo "  - Rows with link: " . number_format($stats['with_link']) . " (" . round($stats['with_link']/$stats['total']*100, 1) . "%)\n\n";

// Step 5: Show new size
echo "[5/5] Calculating new size...\n";

$result = $mysqli->query("
    SELECT
        ROUND(DATA_LENGTH / 1024 / 1024, 2) as data_mb,
        ROUND(INDEX_LENGTH / 1024 / 1024, 2) as index_mb,
        ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as total_mb
    FROM information_schema.TABLES
    WHERE TABLE_NAME = 'reader_unread_cache' AND TABLE_SCHEMA = 'gheop'
");
$newSize = $result->fetch_assoc();

echo "  - New size: {$newSize['data_mb']} MB data + {$newSize['index_mb']} MB index = {$newSize['total_mb']} MB total\n";
echo "  - Size increase: " . round($newSize['total_mb'] - ($size['data_mb'] + $size['index_mb']), 2) . " MB\n\n";

echo str_repeat("═", 64) . "\n";
echo "✓ STEP 4 MIGRATION COMPLETED SUCCESSFULLY\n";
echo str_repeat("═", 64) . "\n";
echo "\n";
echo "Next steps:\n";
echo "1. Update api.php to remove JOIN with reader_item\n";
echo "2. Update triggers to populate cache columns on INSERT\n";
echo "3. Run benchmark to measure performance improvement\n\n";
?>
