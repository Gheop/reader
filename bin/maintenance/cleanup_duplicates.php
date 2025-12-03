#!/usr/bin/env php
<?php
/**
 * Cleanup Duplicate Articles Script
 * More efficient approach: find IDs first, then delete in batches
 */

include(__DIR__ . '/../config/conf.php');
$mysqli = $mysqli;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              CLEANUP DUPLICATE ARTICLES                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$start = microtime(true);

// Step 1: Find articles that were already read
echo "Step 1: Finding already-read duplicate articles...\n";

$result = $mysqli->query("
    SELECT I.id
    FROM reader_item I
    WHERE EXISTS (
        SELECT 1 FROM reader_user_item UI
        INNER JOIN reader_item I2 ON I2.id = UI.id_item
        WHERE UI.id_user = 1
        AND REPLACE(REPLACE(I2.link, 'https://', ''), 'http://', '') = REPLACE(REPLACE(I.link, 'https://', ''), 'http://', '')
        AND I2.id != I.id
    )
    LIMIT 10000
");

$idsToDelete = [];
while ($row = $result->fetch_assoc()) {
    $idsToDelete[] = $row['id'];
}

echo "Found " . count($idsToDelete) . " already-read duplicates\n";

if (count($idsToDelete) > 0) {
    echo "Deleting in batches...\n";

    // Delete in batches of 1000
    $batches = array_chunk($idsToDelete, 1000);
    $totalDeleted = 0;

    foreach ($batches as $batchNum => $batch) {
        $ids = implode(',', $batch);
        $mysqli->query("DELETE FROM reader_item WHERE id IN ($ids)");
        $deleted = $mysqli->affected_rows;
        $totalDeleted += $deleted;
        echo "  Batch " . ($batchNum + 1) . ": deleted $deleted articles\n";
    }

    echo "✓ Total deleted: $totalDeleted articles\n";
} else {
    echo "✓ No duplicates to delete\n";
}

$duration = microtime(true) - $start;
echo sprintf("\n✓ Cleanup complete in %.2f seconds\n", $duration);
?>
