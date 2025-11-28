#!/usr/bin/env php
<?php
/**
 * Performance Monitoring Script
 * Shows database performance metrics and slow queries
 */

include(__DIR__ . '/conf.php');
$mysqli = $_SESSION['mysqli'];

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              DATABASE PERFORMANCE MONITOR                      ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// InnoDB Buffer Pool Stats
echo "InnoDB Buffer Pool:\n";
$result = $mysqli->query("
    SELECT
        POOL_SIZE,
        FREE_BUFFERS,
        DATABASE_PAGES,
        MODIFIED_DATABASE_PAGES,
        NUMBER_PAGES_READ,
        NUMBER_PAGES_WRITTEN
    FROM information_schema.INNODB_BUFFER_POOL_STATS
");
$row = $result->fetch_assoc();
echo sprintf("  Pool Size: %s pages (%.2f GB)\n",
    number_format($row['POOL_SIZE']),
    $row['POOL_SIZE'] * 16 / 1024 / 1024
);
echo sprintf("  Free Buffers: %s\n", number_format($row['FREE_BUFFERS']));
echo sprintf("  Database Pages: %s\n", number_format($row['DATABASE_PAGES']));
echo sprintf("  Modified Pages: %s\n", number_format($row['MODIFIED_DATABASE_PAGES']));
echo sprintf("  Pages Read: %s\n", number_format($row['NUMBER_PAGES_READ']));
echo sprintf("  Pages Written: %s\n", number_format($row['NUMBER_PAGES_WRITTEN']));
echo "\n";

// Query Cache Stats (if enabled)
echo "Query Cache:\n";
$result = $mysqli->query("SHOW STATUS LIKE 'Qcache%'");
while($row = $result->fetch_assoc()) {
    echo sprintf("  %-30s: %s\n", $row['Variable_name'], $row['Value']);
}
echo "\n";

// Table sizes
echo "Table Sizes:\n";
$result = $mysqli->query("
    SELECT
        TABLE_NAME,
        ROUND((DATA_LENGTH + INDEX_LENGTH)/1024/1024, 2) as 'Size MB',
        TABLE_ROWS as 'Rows'
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'gheop' AND TABLE_NAME LIKE 'reader_%'
    ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
    LIMIT 10
");
while($row = $result->fetch_assoc()) {
    echo sprintf("  %-30s: %8s MB (%s rows)\n",
        $row['TABLE_NAME'],
        $row['Size MB'],
        number_format($row['Rows'])
    );
}
echo "\n";

// Recent slow queries from error log
echo "Check error.log for slow queries:\n";
echo "  grep 'SLOW QUERY' /www/reader/error.log | tail -20\n\n";

echo str_repeat("═", 64) . "\n";
echo "✓ MONITORING COMPLETE\n";
echo str_repeat("═", 64) . "\n";
?>
