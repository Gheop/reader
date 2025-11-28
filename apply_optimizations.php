#!/usr/bin/env php
<?php
/**
 * Apply Database Optimizations
 */

include(__DIR__ . '/conf.php');
$mysqli = $_SESSION['mysqli'];

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║           APPLYING DATABASE OPTIMIZATIONS                      ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Step 1: Create backup of reader_unread_cache
echo "[1/5] Creating backup of reader_unread_cache...\n";
$mysqli->query("DROP TABLE IF EXISTS reader_unread_cache_backup");
$mysqli->query("CREATE TABLE reader_unread_cache_backup LIKE reader_unread_cache");
$mysqli->query("INSERT INTO reader_unread_cache_backup SELECT * FROM reader_unread_cache");

$result = $mysqli->query("SELECT COUNT(*) as cnt FROM reader_unread_cache_backup");
$backup = $result->fetch_assoc();
echo "  ✓ Backed up {$backup['cnt']} rows\n\n";

// Step 2: Convert reader_unread_cache to InnoDB
echo "[2/5] Converting reader_unread_cache from MEMORY to InnoDB...\n";
echo "  (This may take a moment...)\n";

$start = microtime(true);
$mysqli->query("ALTER TABLE reader_unread_cache
    ENGINE=InnoDB
    ROW_FORMAT=COMPRESSED
    KEY_BLOCK_SIZE=8");
$duration = microtime(true) - $start;

echo sprintf("  ✓ Converted in %.2f seconds\n\n", $duration);

// Verify conversion
$result = $mysqli->query("
    SELECT ENGINE, ROW_FORMAT, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH
    FROM information_schema.TABLES
    WHERE TABLE_NAME = 'reader_unread_cache' AND TABLE_SCHEMA = 'gheop'
");
$info = $result->fetch_assoc();
echo "  New configuration:\n";
echo "    - Engine: {$info['ENGINE']}\n";
echo "    - Row Format: {$info['ROW_FORMAT']}\n";
echo "    - Rows: {$info['TABLE_ROWS']}\n";
echo "    - Data size: " . formatBytes($info['DATA_LENGTH']) . "\n";
echo "    - Index size: " . formatBytes($info['INDEX_LENGTH']) . "\n\n";

// Step 3: Remove duplicate index
echo "[3/5] Removing duplicate index on reader_user_item...\n";

$start = microtime(true);
$mysqli->query("ALTER TABLE reader_user_item DROP INDEX reader_user_item_id_user_IDX");
$duration = microtime(true) - $start;

if ($mysqli->error) {
    echo "  ⚠ Warning: {$mysqli->error}\n";
} else {
    echo sprintf("  ✓ Removed in %.2f seconds\n\n", $duration);
}

// Step 4: Verify indexes
echo "[4/5] Verifying remaining indexes...\n";
$result = $mysqli->query("
    SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns, NON_UNIQUE
    FROM information_schema.STATISTICS
    WHERE TABLE_NAME = 'reader_user_item' AND TABLE_SCHEMA = 'gheop' AND INDEX_NAME != 'PRIMARY'
    GROUP BY INDEX_NAME, NON_UNIQUE
");

while ($idx = $result->fetch_assoc()) {
    $type = $idx['NON_UNIQUE'] == 0 ? 'UNIQUE' : 'INDEX';
    echo "  - {$type}: {$idx['INDEX_NAME']} ({$idx['columns']})\n";
}
echo "\n";

// Step 5: Calculate space savings
echo "[5/5] Calculating space savings...\n";

$result = $mysqli->query("
    SELECT
        ROUND(DATA_LENGTH / 1024 / 1024, 2) as data_mb,
        ROUND(INDEX_LENGTH / 1024 / 1024, 2) as index_mb,
        ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as total_mb
    FROM information_schema.TABLES
    WHERE TABLE_NAME = 'reader_user_item' AND TABLE_SCHEMA = 'gheop'
");
$size = $result->fetch_assoc();
echo "  reader_user_item total size: {$size['total_mb']} MB (data: {$size['data_mb']} MB, index: {$size['index_mb']} MB)\n";

echo "\n";
echo str_repeat("═", 64) . "\n";
echo "✓ OPTIMIZATIONS COMPLETED SUCCESSFULLY\n";
echo str_repeat("═", 64) . "\n";
echo "\n";
echo "Next step: Run benchmark_db.php to measure performance improvements\n\n";

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
