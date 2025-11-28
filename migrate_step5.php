#!/usr/bin/env php
<?php
/**
 * STEP 5: Refactor hardcoded user columns
 * Create reader_flux_user_stats table and migrate data
 */

include(__DIR__ . '/conf.php');
$mysqli = $mysqli;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║       STEP 5: REFACTOR USER COLUMNS MIGRATION                  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Step 1: Create new table
echo "[1/5] Creating reader_flux_user_stats table...\n";

$start = microtime(true);
$mysqli->query("
    CREATE TABLE IF NOT EXISTS reader_flux_user_stats (
        id_user INT NOT NULL,
        id_flux SMALLINT UNSIGNED NOT NULL,
        unread_count INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id_user, id_flux),
        INDEX idx_user (id_user),
        INDEX idx_flux (id_flux),
        CONSTRAINT fk_stats_flux FOREIGN KEY (id_flux) REFERENCES reader_flux(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$duration = microtime(true) - $start;

if ($mysqli->error) {
    echo "  ✗ Error: {$mysqli->error}\n";
    exit(1);
}

echo sprintf("  ✓ Table created in %.2f seconds\n\n", $duration);

// Step 2: Migrate data from user_1 column
echo "[2/5] Migrating data for user 1...\n";

$start = microtime(true);
$mysqli->query("
    INSERT INTO reader_flux_user_stats (id_user, id_flux, unread_count)
    SELECT 1 as id_user, F.id as id_flux, F.unread_count_user_1 as unread_count
    FROM reader_flux F
    INNER JOIN reader_user_flux UF ON UF.id_flux = F.id
    WHERE UF.id_user = 1
    ON DUPLICATE KEY UPDATE unread_count = VALUES(unread_count)
");
$duration = microtime(true) - $start;
$affected = $mysqli->affected_rows;

if ($mysqli->error) {
    echo "  ✗ Error: {$mysqli->error}\n";
    exit(1);
}

echo sprintf("  ✓ Migrated %s rows in %.2f seconds\n\n", number_format($affected), $duration);

// Step 3: Migrate data from user_2 column
echo "[3/5] Migrating data for user 2...\n";

$start = microtime(true);
$mysqli->query("
    INSERT INTO reader_flux_user_stats (id_user, id_flux, unread_count)
    SELECT 2 as id_user, F.id as id_flux, F.unread_count_user_2 as unread_count
    FROM reader_flux F
    INNER JOIN reader_user_flux UF ON UF.id_flux = F.id
    WHERE UF.id_user = 2
    ON DUPLICATE KEY UPDATE unread_count = VALUES(unread_count)
");
$duration = microtime(true) - $start;
$affected = $mysqli->affected_rows;

if ($mysqli->error) {
    echo "  ✗ Error: {$mysqli->error}\n";
    exit(1);
}

echo sprintf("  ✓ Migrated %s rows in %.2f seconds\n\n", number_format($affected), $duration);

// Step 4: Verify data integrity
echo "[4/5] Verifying data integrity...\n";

// Check totals match
$result = $mysqli->query("
    SELECT
        COUNT(*) as total,
        SUM(unread_count) as sum_unread
    FROM reader_flux_user_stats
    WHERE id_user = 1
");
$user1 = $result->fetch_assoc();

$result = $mysqli->query("
    SELECT
        COUNT(*) as total,
        SUM(unread_count_user_1) as sum_unread
    FROM reader_flux F
    INNER JOIN reader_user_flux UF ON UF.id_flux = F.id
    WHERE UF.id_user = 1
");
$user1_old = $result->fetch_assoc();

echo "  User 1:\n";
echo "    - New table: " . number_format($user1['total']) . " feeds, " . number_format($user1['sum_unread']) . " unread\n";
echo "    - Old columns: " . number_format($user1_old['total']) . " feeds, " . number_format($user1_old['sum_unread']) . " unread\n";

if ($user1['sum_unread'] == $user1_old['sum_unread']) {
    echo "    ✓ Counts match!\n";
} else {
    echo "    ✗ MISMATCH!\n";
    exit(1);
}

$result = $mysqli->query("
    SELECT
        COUNT(*) as total,
        SUM(unread_count) as sum_unread
    FROM reader_flux_user_stats
    WHERE id_user = 2
");
$user2 = $result->fetch_assoc();

$result = $mysqli->query("
    SELECT
        COUNT(*) as total,
        SUM(unread_count_user_2) as sum_unread
    FROM reader_flux F
    INNER JOIN reader_user_flux UF ON UF.id_flux = F.id
    WHERE UF.id_user = 2
");
$user2_old = $result->fetch_assoc();

echo "  User 2:\n";
echo "    - New table: " . number_format($user2['total']) . " feeds, " . number_format($user2['sum_unread']) . " unread\n";
echo "    - Old columns: " . number_format($user2_old['total']) . " feeds, " . number_format($user2_old['sum_unread']) . " unread\n";

if ($user2['sum_unread'] == $user2_old['sum_unread']) {
    echo "    ✓ Counts match!\n\n";
} else {
    echo "    ✗ MISMATCH!\n";
    exit(1);
}

// Step 5: Show table size
echo "[5/5] Analyzing table size...\n";

$result = $mysqli->query("
    SELECT
        TABLE_ROWS as `rows`,
        ROUND(DATA_LENGTH / 1024 / 1024, 2) as data_mb,
        ROUND(INDEX_LENGTH / 1024 / 1024, 2) as index_mb,
        ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as total_mb
    FROM information_schema.TABLES
    WHERE TABLE_NAME = 'reader_flux_user_stats' AND TABLE_SCHEMA = 'gheop'
");
$size = $result->fetch_assoc();

echo "  - Total rows: " . number_format($size['rows']) . "\n";
echo "  - Data size: {$size['data_mb']} MB\n";
echo "  - Index size: {$size['index_mb']} MB\n";
echo "  - Total size: {$size['total_mb']} MB\n\n";

echo str_repeat("═", 64) . "\n";
echo "✓ STEP 5 MIGRATION COMPLETED SUCCESSFULLY\n";
echo str_repeat("═", 64) . "\n";
echo "\n";
echo "Next steps:\n";
echo "1. Update api.php to use reader_flux_user_stats\n";
echo "2. Update read.php to update new table\n";
echo "3. Run benchmark to measure performance\n";
echo "4. Drop old columns once verified (optional)\n\n";
?>
