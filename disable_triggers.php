#!/usr/bin/env php
<?php
/**
 * STEP 3: Disable all triggers
 * PHP now handles cache/counter updates manually
 */

include(__DIR__ . '/conf.php');
$mysqli = $mysqli;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              STEP 3: DISABLE TRIGGERS                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// List of all triggers to disable
$triggers = [
    'cache_after_item_insert',
    'update_unread_count_after_item_insert',
    'update_unread_count_after_item_delete',
    'cache_after_item_delete',
    'update_unread_count_after_read',
    'cache_after_read',
    'update_unread_count_after_unread',
    'cache_after_unread'
];

echo "Disabling " . count($triggers) . " triggers...\n\n";

$disabled = 0;
foreach ($triggers as $trigger) {
    echo "  [" . ($disabled + 1) . "/" . count($triggers) . "] Dropping trigger: $trigger... ";

    $result = $mysqli->query("DROP TRIGGER IF EXISTS $trigger");

    if ($mysqli->error) {
        echo "ERROR: {$mysqli->error}\n";
    } else {
        echo "✓\n";
        $disabled++;
    }
}

echo "\n";
echo str_repeat("═", 64) . "\n";
echo "✓ DISABLED $disabled TRIGGERS\n";
echo str_repeat("═", 64) . "\n";
echo "\n";

// Verify no triggers remain
$result = $mysqli->query("SHOW TRIGGERS");
$remaining = $result->num_rows;

if ($remaining > 0) {
    echo "⚠ WARNING: $remaining triggers still active:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Trigger']}\n";
    }
} else {
    echo "✓ Verified: No triggers remaining\n";
}

echo "\nNext step: Run benchmark to measure performance improvement\n\n";
?>
