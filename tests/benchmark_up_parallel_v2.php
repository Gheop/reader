#!/usr/bin/env php
<?php
/**
 * Benchmark v2: Compare different query strategies
 */

require_once __DIR__ . '/../config/conf.php';

echo "=== up_parallel.php Benchmark v2 ===\n\n";

// Get feeds with varying item counts
$feeds = db()->fetchAll("
    SELECT F.id, F.title,
           (SELECT COUNT(*) FROM reader_item WHERE id_flux = F.id) as item_count,
           (SELECT COUNT(*) FROM reader_item_archive WHERE id_flux = F.id) as archive_count
    FROM reader_flux F
    INNER JOIN reader_user_flux UF ON UF.id_flux = F.id
    WHERE F.rss IS NOT NULL
    ORDER BY RAND()
    LIMIT 10
");

echo "Testing with " . count($feeds) . " feeds\n\n";

// Get some real GUIDs and links for realistic testing
$realArticles = db()->fetchAll("
    SELECT id_flux, guid, link FROM reader_item
    ORDER BY RAND() LIMIT 50
");
$realByFeed = [];
foreach ($realArticles as $a) {
    $realByFeed[$a['id_flux']][] = $a;
}

// ========================================
// Method 1: Current (UNION + LIKE)
// ========================================
echo "--- Method 1: Current (UNION + LIKE) ---\n";
$m1_total = 0;

foreach ($feeds as $feed) {
    $feedId = $feed['id'];
    $start = microtime(true);

    for ($i = 0; $i < 5; $i++) {
        // Use real GUID if available, else fake
        if (isset($realByFeed[$feedId][$i])) {
            $guid = $realByFeed[$feedId][$i]['guid'];
            $link = $realByFeed[$feedId][$i]['link'];
        } else {
            $guid = 'test-' . $feedId . '-' . $i;
            $link = 'https://example.com/' . $feedId . '/' . $i;
        }

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
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $m1_total += $elapsed;
    echo "  Feed {$feedId} ({$feed['item_count']}+{$feed['archive_count']} items): " . round($elapsed, 2) . "ms\n";
}
echo "Total: " . round($m1_total, 2) . "ms\n\n";

// ========================================
// Method 2: GUID only (no LIKE)
// ========================================
echo "--- Method 2: GUID only (no LIKE, no reader_user_item) ---\n";
$m2_total = 0;

foreach ($feeds as $feed) {
    $feedId = $feed['id'];
    $start = microtime(true);

    for ($i = 0; $i < 5; $i++) {
        if (isset($realByFeed[$feedId][$i])) {
            $guid = $realByFeed[$feedId][$i]['guid'];
        } else {
            $guid = 'test-' . $feedId . '-' . $i;
        }

        $stmt = $mysqli->prepare("
            SELECT 1 FROM reader_item WHERE id_flux = ? AND guid = ?
            UNION
            SELECT 1 FROM reader_item_archive WHERE id_flux = ? AND guid = ?
            LIMIT 1
        ");
        $stmt->bind_param("isis", $feedId, $guid, $feedId, $guid);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $m2_total += $elapsed;
    echo "  Feed {$feedId}: " . round($elapsed, 2) . "ms\n";
}
echo "Total: " . round($m2_total, 2) . "ms\n\n";

// ========================================
// Method 3: Two separate simple queries
// ========================================
echo "--- Method 3: Separate queries (guid first, then link if needed) ---\n";
$m3_total = 0;

foreach ($feeds as $feed) {
    $feedId = $feed['id'];
    $start = microtime(true);

    for ($i = 0; $i < 5; $i++) {
        if (isset($realByFeed[$feedId][$i])) {
            $guid = $realByFeed[$feedId][$i]['guid'];
            $link = $realByFeed[$feedId][$i]['link'];
        } else {
            $guid = 'test-' . $feedId . '-' . $i;
            $link = 'https://example.com/' . $feedId . '/' . $i;
        }

        // First: quick GUID check
        $exists = db()->fetchColumn("
            SELECT 1 FROM reader_item WHERE id_flux = ? AND guid = ? LIMIT 1
        ", [$feedId, $guid]);

        if (!$exists) {
            $exists = db()->fetchColumn("
                SELECT 1 FROM reader_item_archive WHERE id_flux = ? AND guid = ? LIMIT 1
            ", [$feedId, $guid]);
        }

        // Only check by link if GUID not found (rare case)
        if (!$exists) {
            $exists = db()->fetchColumn("
                SELECT 1 FROM reader_item WHERE id_flux = ? AND link = ? LIMIT 1
            ", [$feedId, $link]);
        }
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $m3_total += $elapsed;
    echo "  Feed {$feedId}: " . round($elapsed, 2) . "ms\n";
}
echo "Total: " . round($m3_total, 2) . "ms\n\n";

// ========================================
// Method 4: Batch pre-load GUIDs only (lightweight)
// ========================================
echo "--- Method 4: Batch pre-load GUIDs only ---\n";
$m4_total = 0;

foreach ($feeds as $feed) {
    $feedId = $feed['id'];
    $start = microtime(true);

    // Pre-load just GUIDs (not links)
    $guids = db()->fetchAll("
        SELECT guid FROM reader_item WHERE id_flux = ? AND guid IS NOT NULL
        UNION
        SELECT guid FROM reader_item_archive WHERE id_flux = ? AND guid IS NOT NULL
    ", [$feedId, $feedId]);

    $guidSet = [];
    foreach ($guids as $row) {
        $guidSet[$row['guid']] = true;
    }

    for ($i = 0; $i < 5; $i++) {
        if (isset($realByFeed[$feedId][$i])) {
            $guid = $realByFeed[$feedId][$i]['guid'];
        } else {
            $guid = 'test-' . $feedId . '-' . $i;
        }

        $exists = isset($guidSet[$guid]);
    }

    $elapsed = (microtime(true) - $start) * 1000;
    $m4_total += $elapsed;
    echo "  Feed {$feedId}: " . round($elapsed, 2) . "ms\n";
}
echo "Total: " . round($m4_total, 2) . "ms\n\n";

// ========================================
// SUMMARY
// ========================================
echo "=== SUMMARY ===\n";
echo "Method 1 (Current):     " . round($m1_total, 2) . "ms (baseline)\n";
echo "Method 2 (GUID only):   " . round($m2_total, 2) . "ms (" . round(($m1_total - $m2_total) / $m1_total * 100, 1) . "% " . ($m2_total < $m1_total ? "faster" : "slower") . ")\n";
echo "Method 3 (Separate):    " . round($m3_total, 2) . "ms (" . round(($m1_total - $m3_total) / $m1_total * 100, 1) . "% " . ($m3_total < $m1_total ? "faster" : "slower") . ")\n";
echo "Method 4 (Batch GUID):  " . round($m4_total, 2) . "ms (" . round(($m1_total - $m4_total) / $m1_total * 100, 1) . "% " . ($m4_total < $m1_total ? "faster" : "slower") . ")\n";

$best = min($m1_total, $m2_total, $m3_total, $m4_total);
$bestName = match($best) {
    $m1_total => "Method 1 (Current)",
    $m2_total => "Method 2 (GUID only)",
    $m3_total => "Method 3 (Separate)",
    $m4_total => "Method 4 (Batch GUID)",
};
echo "\nBest: $bestName\n";
