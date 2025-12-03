#!/usr/bin/env php
<?php
/**
 * Fix Missing GUIDs Script
 * Generates synthetic GUIDs for articles that don't have one
 * This prevents re-importing old articles after archiving
 */

include(__DIR__ . '/../config/conf.php');
$mysqli = $mysqli;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              FIX MISSING GUIDS                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Count articles without proper GUID
$result = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM reader_item
    WHERE guid IS NULL OR guid = '' OR LENGTH(guid) < 5
");
$countMain = $result->fetch_assoc()['cnt'];

$result = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM reader_item_archive
    WHERE guid IS NULL OR guid = '' OR LENGTH(guid) < 5
");
$countArchive = $result->fetch_assoc()['cnt'];

echo "Articles without proper GUID:\n";
echo "  - reader_item: " . number_format($countMain) . "\n";
echo "  - reader_item_archive: " . number_format($countArchive) . "\n";
echo "  - Total: " . number_format($countMain + $countArchive) . "\n\n";

if ($countMain + $countArchive == 0) {
    echo "✓ No articles to fix.\n";
    exit(0);
}

echo "Generating synthetic GUIDs...\n\n";

$start = microtime(true);

// Fix reader_item
echo "Updating reader_item...\n";
$mysqli->query("
    UPDATE reader_item
    SET guid = CONCAT('synthetic-', MD5(CONCAT(id_flux, '|', link)))
    WHERE guid IS NULL OR guid = '' OR LENGTH(guid) < 5
");
$fixedMain = $mysqli->affected_rows;
echo "✓ Fixed " . number_format($fixedMain) . " articles\n";

// Fix reader_item_archive
echo "Updating reader_item_archive...\n";
// NOTE: Don't use title in hash (YouTube A/B testing changes titles)
$mysqli->query("
    UPDATE reader_item_archive
    SET guid = CONCAT('synthetic-', MD5(CONCAT(id_flux, '|', link)))
    WHERE guid IS NULL OR guid = '' OR LENGTH(guid) < 5
");
$fixedArchive = $mysqli->affected_rows;
echo "✓ Fixed " . number_format($fixedArchive) . " articles\n";

$duration = microtime(true) - $start;

echo "\n";
echo sprintf("✓ Fix complete in %.2f seconds\n", $duration);
echo "Total fixed: " . number_format($fixedMain + $fixedArchive) . " articles\n";
echo "\n";

// Verify
$result = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM reader_item
    WHERE guid IS NULL OR guid = '' OR LENGTH(guid) < 5
");
$remainingMain = $result->fetch_assoc()['cnt'];

$result = $mysqli->query("
    SELECT COUNT(*) as cnt
    FROM reader_item_archive
    WHERE guid IS NULL OR guid = '' OR LENGTH(guid) < 5
");
$remainingArchive = $result->fetch_assoc()['cnt'];

if ($remainingMain + $remainingArchive == 0) {
    echo "✓ Verification: All articles now have valid GUIDs\n";
} else {
    echo "⚠ Warning: " . number_format($remainingMain + $remainingArchive) . " articles still without GUID\n";
}

echo "\n";
echo str_repeat("═", 64) . "\n";
echo "✓ GUID FIX COMPLETE\n";
echo str_repeat("═", 64) . "\n";
?>
