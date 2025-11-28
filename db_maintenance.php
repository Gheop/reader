#!/usr/bin/env php
<?php
/**
 * Database Maintenance Script
 * - Analyzes tables to update statistics
 * - Checks index usage
 * - Reports fragmentation
 * Run this monthly or after major data changes
 */

include('/www/conf.php');
$mysqli = $_SESSION['mysqli'];

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              DATABASE MAINTENANCE                              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Tables to maintain
$tables = [
    'reader_item',
    'reader_user_item',
    'reader_unread_cache',
    'reader_flux_user_stats',
    'reader_user_flux',
    'reader_flux'
];

echo "Running ANALYZE TABLE to update statistics...\n\n";

foreach ($tables as $table) {
    echo "Analyzing $table... ";
    $start = microtime(true);
    $result = $mysqli->query("ANALYZE TABLE $table");
    $duration = (microtime(true) - $start) * 1000;

    $row = $result->fetch_assoc();
    $status = $row['Msg_text'];

    echo sprintf("✓ %s (%.2fms)\n", $status, $duration);
}

echo "\n";
echo str_repeat("─", 64) . "\n";
echo "Checking table fragmentation...\n\n";

$result = $mysqli->query("
    SELECT
        TABLE_NAME,
        ROUND(DATA_LENGTH/1024/1024, 2) as 'Data MB',
        ROUND(DATA_FREE/1024/1024, 2) as 'Free MB',
        ROUND(100 * DATA_FREE / (DATA_LENGTH + DATA_FREE), 2) as 'Fragmentation %'
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'gheop'
        AND TABLE_NAME LIKE 'reader_%'
        AND DATA_FREE > 0
    ORDER BY DATA_FREE DESC
");

$hasFragmentation = false;
while ($row = $result->fetch_assoc()) {
    if ($row['Fragmentation %'] > 10) {
        if (!$hasFragmentation) {
            echo "Tables with > 10% fragmentation:\n";
            $hasFragmentation = true;
        }
        echo sprintf("  %-30s: %.2f MB free (%.1f%% fragmented)\n",
            $row['TABLE_NAME'],
            $row['Free MB'],
            $row['Fragmentation %']
        );
    }
}

if (!$hasFragmentation) {
    echo "✓ No significant fragmentation detected\n";
} else {
    echo "\nTo defragment, run: OPTIMIZE TABLE table_name;\n";
}

echo "\n";
echo str_repeat("─", 64) . "\n";
echo "Index cardinality check...\n\n";

$result = $mysqli->query("
    SELECT
        TABLE_NAME,
        INDEX_NAME,
        CARDINALITY,
        SEQ_IN_INDEX
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'gheop'
        AND TABLE_NAME LIKE 'reader_%'
        AND CARDINALITY IS NOT NULL
        AND CARDINALITY < 100
        AND INDEX_NAME != 'PRIMARY'
    ORDER BY CARDINALITY
");

$lowCard = false;
while ($row = $result->fetch_assoc()) {
    if (!$lowCard) {
        echo "Indexes with low cardinality (< 100):\n";
        $lowCard = true;
    }
    echo sprintf("  %-30s.%-30s: %d\n",
        $row['TABLE_NAME'],
        $row['INDEX_NAME'],
        $row['CARDINALITY']
    );
}

if (!$lowCard) {
    echo "✓ All indexes have good cardinality\n";
}

echo "\n";
echo str_repeat("═", 64) . "\n";
echo "✓ MAINTENANCE COMPLETE\n";
echo str_repeat("═", 64) . "\n";
?>
