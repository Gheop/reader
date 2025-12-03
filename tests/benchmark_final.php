#!/usr/bin/env php
<?php
/**
 * Final Benchmark: Compare old vs new implementation
 */

require_once __DIR__ . '/../config/conf.php';

echo "=== FINAL BENCHMARK: Old vs Optimized ===\n\n";

// Get 20 feeds for more accurate results
$feeds = db()->fetchAll("
    SELECT F.id, F.title,
           (SELECT COUNT(*) FROM reader_item WHERE id_flux = F.id) as item_count,
           (SELECT COUNT(*) FROM reader_item_archive WHERE id_flux = F.id) as archive_count
    FROM reader_flux F
    INNER JOIN reader_user_flux UF ON UF.id_flux = F.id
    WHERE F.rss IS NOT NULL
    ORDER BY RAND()
    LIMIT 20
");

echo "Testing with " . count($feeds) . " feeds\n";
$totalItems = array_sum(array_column($feeds, 'item_count'));
$totalArchive = array_sum(array_column($feeds, 'archive_count'));
echo "Total items in DB: {$totalItems} current + {$totalArchive} archived\n\n";

// ========================================
// OLD METHOD (simulate what was before)
// ========================================
echo "--- OLD METHOD (per-article UNION + LIKE) ---\n";
$old_total = 0;
$old_queries = 0;

foreach ($feeds as $feed) {
    $feedId = $feed['id'];
    $start = microtime(true);

    // Simulate 5 article checks (like the actual code does)
    for ($i = 0; $i < 5; $i++) {
        $guid = 'test-guid-' . $feedId . '-' . $i . '-' . microtime(true);
        $link = 'https://example.com/article/' . $feedId . '/' . $i;
        $link_clean = preg_replace('/^https?:\/\//', '', $link);
        $pattern = '%' . $link_clean . '%';

        $stmt = $mysqli->prepare("
            SELECT 1 FROM reader_item
            WHERE id_flux = ? AND (guid = ? OR link LIKE CONVERT(? USING utf8mb3) COLLATE utf8mb3_bin)
            UNION
            SELECT 1 FROM reader_item_archive
            WHERE id_flux = ? AND (guid = ? OR link LIKE CONVERT(? USING utf8mb3) COLLATE utf8mb3_bin)
            UNION
            SELECT 1 FROM reader_user_item UI
            INNER JOIN reader_item I ON I.id = UI.id_item
            WHERE I.id_flux = ? AND I.link LIKE CONVERT(? USING utf8mb3) COLLATE utf8mb3_bin
            LIMIT 1
        ");
        $stmt->bind_param("isssssss", $feedId, $guid, $pattern, $feedId, $guid, $pattern, $feedId, $pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        $old_queries++;
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $old_total += $elapsed;
}

echo "Total time: " . round($old_total, 2) . "ms\n";
echo "Total queries: {$old_queries}\n";
echo "Average per feed: " . round($old_total / count($feeds), 2) . "ms\n";
echo "Average per article check: " . round($old_total / $old_queries, 2) . "ms\n\n";

// ========================================
// NEW METHOD (batch pre-load GUIDs)
// ========================================
echo "--- NEW METHOD (batch pre-load GUIDs) ---\n";
$new_total = 0;
$new_queries = 0;

foreach ($feeds as $feed) {
    $feedId = $feed['id'];
    $start = microtime(true);

    // Pre-load GUIDs (1 query per feed)
    $stmt = $mysqli->prepare("
        SELECT guid FROM reader_item WHERE id_flux = ? AND guid IS NOT NULL
        UNION
        SELECT guid FROM reader_item_archive WHERE id_flux = ? AND guid IS NOT NULL
    ");
    $stmt->bind_param("ii", $feedId, $feedId);
    $stmt->execute();
    $guidsResult = $stmt->get_result();
    $existingGuids = [];
    while ($row = $guidsResult->fetch_row()) {
        $existingGuids[$row[0]] = true;
    }
    $stmt->close();
    $new_queries++;

    // Simulate 5 article checks (now just PHP lookups)
    for ($i = 0; $i < 5; $i++) {
        $guid = 'test-guid-' . $feedId . '-' . $i . '-' . microtime(true);
        $exists = isset($existingGuids[$guid]); // Instant PHP lookup
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $new_total += $elapsed;
}

echo "Total time: " . round($new_total, 2) . "ms\n";
echo "Total queries: {$new_queries}\n";
echo "Average per feed: " . round($new_total / count($feeds), 2) . "ms\n";
echo "Average per article check: " . round($new_total / ($new_queries * 5), 2) . "ms\n\n";

// ========================================
// COMPARISON
// ========================================
echo "=== RESULTS ===\n";
$speedup = $old_total / $new_total;
$savings_ms = $old_total - $new_total;
$savings_pct = ($savings_ms / $old_total) * 100;
$query_reduction = (1 - ($new_queries / $old_queries)) * 100;

echo "Speed improvement: " . round($speedup, 1) . "x faster\n";
echo "Time saved: " . round($savings_ms, 2) . "ms (" . round($savings_pct, 1) . "%)\n";
echo "Query reduction: " . round($query_reduction, 1) . "% fewer queries ({$old_queries} → {$new_queries})\n";

if ($speedup >= 2) {
    echo "\n✅ OPTIMIZATION SUCCESSFUL!\n";
} elseif ($speedup >= 1.2) {
    echo "\n✓ Moderate improvement\n";
} else {
    echo "\n⚠ Marginal improvement\n";
}

echo "\n";
