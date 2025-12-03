#!/usr/bin/env php
<?php
/**
 * Benchmark script for up_parallel.php article existence check
 *
 * Measures the performance of checking if articles already exist
 */

require_once __DIR__ . '/../config/conf.php';

echo "=== up_parallel.php Benchmark ===\n\n";

// Get a sample of feeds with articles
$feeds = db()->fetchAll("
    SELECT F.id, F.title, F.rss,
           (SELECT COUNT(*) FROM reader_item WHERE id_flux = F.id) as item_count
    FROM reader_flux F
    INNER JOIN reader_user_flux UF ON UF.id_flux = F.id
    WHERE F.rss IS NOT NULL AND F.rss != ''
    ORDER BY RAND()
    LIMIT 10
");

echo "Testing with " . count($feeds) . " feeds\n\n";

// ========================================
// CURRENT METHOD: Individual queries per article
// ========================================
echo "--- Method 1: Current (individual queries) ---\n";

$method1_times = [];
$method1_total = 0;

foreach ($feeds as $feed) {
    $feedId = $feed['id'];

    // Simulate checking 5 articles (like in the actual code)
    $start = microtime(true);

    for ($i = 0; $i < 5; $i++) {
        // Generate test data similar to real articles
        $guid = 'test-guid-' . $feedId . '-' . $i . '-' . time();
        $link = 'https://example.com/article-' . $feedId . '-' . $i;
        $link_without_protocol = preg_replace('/^https?:\/\//', '', $link);
        $searchPattern = '%' . $link_without_protocol . '%';

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

        $stmt->bind_param("isssssss", $feedId, $guid, $searchPattern, $feedId, $guid, $searchPattern, $feedId, $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $method1_times[] = $elapsed;
    $method1_total += $elapsed;

    echo "  Feed {$feedId} ({$feed['item_count']} items): " . round($elapsed, 2) . "ms\n";
}

$method1_avg = $method1_total / count($feeds);
echo "\nMethod 1 Total: " . round($method1_total, 2) . "ms\n";
echo "Method 1 Average per feed: " . round($method1_avg, 2) . "ms\n";
echo "Method 1 Average per article check: " . round($method1_avg / 5, 2) . "ms\n\n";

// ========================================
// OPTIMIZED METHOD: Batch pre-load then check in PHP
// ========================================
echo "--- Method 2: Optimized (batch pre-load) ---\n";

$method2_times = [];
$method2_total = 0;

foreach ($feeds as $feed) {
    $feedId = $feed['id'];

    $start = microtime(true);

    // Pre-load all existing GUIDs and links for this feed in ONE query
    $existing = db()->fetchAll("
        SELECT guid, REPLACE(REPLACE(link, 'https://', ''), 'http://', '') as link_clean
        FROM reader_item WHERE id_flux = ?
        UNION
        SELECT guid, REPLACE(REPLACE(link, 'https://', ''), 'http://', '') as link_clean
        FROM reader_item_archive WHERE id_flux = ?
    ", [$feedId, $feedId]);

    // Build lookup sets
    $existingGuids = [];
    $existingLinks = [];
    foreach ($existing as $row) {
        if ($row['guid']) $existingGuids[$row['guid']] = true;
        if ($row['link_clean']) $existingLinks[$row['link_clean']] = true;
    }

    // Simulate checking 5 articles (now just PHP array lookups)
    for ($i = 0; $i < 5; $i++) {
        $guid = 'test-guid-' . $feedId . '-' . $i . '-' . time();
        $link = 'https://example.com/article-' . $feedId . '-' . $i;
        $link_without_protocol = preg_replace('/^https?:\/\//', '', $link);

        // Check in PHP (instant)
        $exists = isset($existingGuids[$guid]) || isset($existingLinks[$link_without_protocol]);
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $method2_times[] = $elapsed;
    $method2_total += $elapsed;

    echo "  Feed {$feedId} ({$feed['item_count']} items): " . round($elapsed, 2) . "ms\n";
}

$method2_avg = $method2_total / count($feeds);
echo "\nMethod 2 Total: " . round($method2_total, 2) . "ms\n";
echo "Method 2 Average per feed: " . round($method2_avg, 2) . "ms\n";
echo "Method 2 Average per article check: " . round($method2_avg / 5, 2) . "ms\n\n";

// ========================================
// COMPARISON
// ========================================
echo "=== COMPARISON ===\n";
$speedup = $method1_total / $method2_total;
$savings = $method1_total - $method2_total;

echo "Speedup: " . round($speedup, 1) . "x faster\n";
echo "Time saved: " . round($savings, 2) . "ms (" . round($savings / $method1_total * 100, 1) . "%)\n";

if ($speedup > 1.5) {
    echo "\n✓ Optimization is WORTH IT!\n";
} else {
    echo "\n⚠ Optimization provides marginal improvement\n";
}

echo "\n";
