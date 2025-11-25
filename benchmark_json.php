#!/usr/bin/env php
<?php
/**
 * Benchmark: String Concatenation vs json_encode()
 * Tests performance with real data from the database
 */

include('/www/conf.php');

$userId = 2;
$limit = 200;

// Fetch real data
$articlesSql = "
    SELECT
        I.id,
        I.title,
        I.pubdate,
        I.author,
        I.description,
        I.link,
        I.id_flux,
        F.title as feed_title,
        F.description as feed_description,
        F.link as feed_link
    FROM reader_unread_cache C
    INNER JOIN reader_item I ON C.id_item = I.id
    INNER JOIN reader_flux F ON I.id_flux = F.id
    WHERE C.id_user = ?
    ORDER BY I.pubdate DESC
    LIMIT ?
";

$stmt = $mysqli->prepare($articlesSql);
$stmt->bind_param("ii", $userId, $limit);
$stmt->execute();
$result = $stmt->get_result();

$articles = [];
while ($row = $result->fetch_assoc()) {
    $articles[] = $row;
}

echo "Testing with " . count($articles) . " articles\n\n";

// ============================================================================
// METHOD 1: String Concatenation (current)
// ============================================================================

$iterations = 100;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $articlesJson = '{';
    $first = true;
    foreach ($articles as $row) {
        if (!$first) $articlesJson .= ',';
        $first = false;

        // Escape quotes and backslashes
        $title = str_replace(['\\', '"'], ['\\\\', '\\"'], $row['title'] ?? '');
        $desc = str_replace(['\\', '"'], ['\\\\', '\\"'], $row['description'] ?? '');
        $author = str_replace(['\\', '"'], ['\\\\', '\\"'], $row['author'] ?? '');
        $feed_title = str_replace(['\\', '"'], ['\\\\', '\\"'], $row['feed_title'] ?? '');
        $feed_desc = str_replace(['\\', '"'], ['\\\\', '\\"'], $row['feed_description'] ?? '');

        $articlesJson .= '"' . $row['id'] . '":{"t":"' . $title . '","p":"' . ($row['pubdate'] ?? '') . '"';

        if (!empty($row['author'])) {
            $articlesJson .= ',"a":"' . $author . '"';
        }

        $articlesJson .= ',"d":"' . $desc . '","l":"' . ($row['link'] ?? '') . '","o":"' . ($row['feed_link'] ?? '') . '"';
        $articlesJson .= ',"f":"' . ($row['id_flux'] ?? '') . '","n":"' . $feed_title . '","e":"' . $feed_desc . '"}';
    }
    $articlesJson .= '}';
}
$concatTime = microtime(true) - $start;

$concatSize = strlen($articlesJson);

// ============================================================================
// METHOD 2: json_encode()
// ============================================================================

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $articlesArray = [];
    foreach ($articles as $row) {
        $item = [
            't' => $row['title'] ?? '',
            'p' => $row['pubdate'] ?? '',
            'd' => $row['description'] ?? '',
            'l' => $row['link'] ?? '',
            'o' => $row['feed_link'] ?? '',
            'f' => $row['id_flux'] ?? '',
            'n' => $row['feed_title'] ?? '',
            'e' => $row['feed_description'] ?? ''
        ];

        if (!empty($row['author'])) {
            $item['a'] = $row['author'];
        }

        $articlesArray[$row['id']] = $item;
    }
    $articlesJson = json_encode($articlesArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
$jsonTime = microtime(true) - $start;

$jsonSize = strlen($articlesJson);

// ============================================================================
// RESULTS
// ============================================================================

echo "=== BENCHMARK RESULTS ($iterations iterations) ===\n\n";

echo "String Concatenation:\n";
echo "  Time: " . round($concatTime * 1000, 2) . " ms\n";
echo "  Avg:  " . round($concatTime * 1000 / $iterations, 3) . " ms per iteration\n";
echo "  Size: " . number_format($concatSize) . " bytes\n\n";

echo "json_encode():\n";
echo "  Time: " . round($jsonTime * 1000, 2) . " ms\n";
echo "  Avg:  " . round($jsonTime * 1000 / $iterations, 3) . " ms per iteration\n";
echo "  Size: " . number_format($jsonSize) . " bytes\n\n";

$speedDiff = (($concatTime - $jsonTime) / $concatTime) * 100;
$sizeDiff = (($jsonSize - $concatSize) / $concatSize) * 100;

echo "Comparison:\n";
if ($jsonTime < $concatTime) {
    echo "  json_encode() is " . round(abs($speedDiff), 1) . "% FASTER\n";
} else {
    echo "  String concat is " . round(abs($speedDiff), 1) . "% FASTER\n";
}

if ($jsonSize < $concatSize) {
    echo "  json_encode() produces " . round(abs($sizeDiff), 1) . "% SMALLER output\n";
} else {
    echo "  String concat produces " . round(abs($sizeDiff), 1) . "% SMALLER output\n";
}

echo "\nSize difference: " . number_format($jsonSize - $concatSize) . " bytes\n";

// ============================================================================
// GZIP COMPRESSION TEST
// ============================================================================

echo "\n=== GZIP COMPRESSION ===\n\n";

// Test with one iteration
$articlesArray = [];
foreach ($articles as $row) {
    $item = [
        't' => $row['title'] ?? '',
        'p' => $row['pubdate'] ?? '',
        'd' => $row['description'] ?? '',
        'l' => $row['link'] ?? '',
        'o' => $row['feed_link'] ?? '',
        'f' => $row['id_flux'] ?? '',
        'n' => $row['feed_title'] ?? '',
        'e' => $row['feed_description'] ?? ''
    ];
    if (!empty($row['author'])) {
        $item['a'] = $row['author'];
    }
    $articlesArray[$row['id']] = $item;
}

$concatOutput = $articlesJson; // From last iteration above
$jsonOutput = json_encode($articlesArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$concatGzipped = gzencode($concatOutput, 6);
$jsonGzipped = gzencode($jsonOutput, 6);

echo "String Concatenation:\n";
echo "  Uncompressed: " . number_format(strlen($concatOutput)) . " bytes\n";
echo "  Gzipped:      " . number_format(strlen($concatGzipped)) . " bytes (" . round((1 - strlen($concatGzipped) / strlen($concatOutput)) * 100, 1) . "% reduction)\n\n";

echo "json_encode():\n";
echo "  Uncompressed: " . number_format(strlen($jsonOutput)) . " bytes\n";
echo "  Gzipped:      " . number_format(strlen($jsonGzipped)) . " bytes (" . round((1 - strlen($jsonGzipped) / strlen($jsonOutput)) * 100, 1) . "% reduction)\n\n";

$gzipDiff = strlen($jsonGzipped) - strlen($concatGzipped);
echo "Gzipped size difference: " . number_format($gzipDiff) . " bytes\n";

if ($gzipDiff < 0) {
    echo "json_encode() is " . number_format(abs($gzipDiff)) . " bytes SMALLER when gzipped\n";
} else {
    echo "String concat is " . number_format(abs($gzipDiff)) . " bytes SMALLER when gzipped\n";
}
?>
