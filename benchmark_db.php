#!/usr/bin/env php
<?php
/**
 * Database Performance Benchmark
 * Tests the main queries used by the application
 */

include(__DIR__ . '/conf.php');
$mysqli = $mysqli;

// Configuration
$userId = 1; // SiB
$iterations = 100;
$warmupIterations = 10;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        DATABASE PERFORMANCE BENCHMARK - READER APP             ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Get table info
$result = $mysqli->query("SHOW TABLE STATUS LIKE 'reader_unread_cache'");
$tableInfo = $result->fetch_assoc();
echo "Current Configuration:\n";
echo "  - reader_unread_cache Engine: {$tableInfo['Engine']}\n";
echo "  - Rows in cache: " . number_format($tableInfo['Rows']) . "\n";
echo "  - Data size: " . formatBytes($tableInfo['Data_length']) . "\n";
echo "  - Index size: " . formatBytes($tableInfo['Index_length']) . "\n";

// Check indexes on reader_user_item
$indexes = $mysqli->query("SHOW INDEXES FROM reader_user_item");
echo "\n  - reader_user_item indexes:\n";
while ($idx = $indexes->fetch_assoc()) {
    if ($idx['Key_name'] != 'PRIMARY') {
        echo "    * {$idx['Key_name']} ({$idx['Column_name']})\n";
    }
}

echo "\n" . str_repeat("─", 64) . "\n\n";

// Benchmark queries
$benchmarks = [
    'Menu Query (all feeds with unread)' => function() use ($mysqli, $userId) {
        $counterColumn = $userId == 1 ? 'unread_count_user_1' : 'unread_count_user_2';
        $stmt = $mysqli->prepare("
            SELECT F.id, F.title, F.$counterColumn as n, F.description, F.link
            FROM reader_flux F
            INNER JOIN reader_user_flux UF ON UF.id_flux = F.id
            WHERE UF.id_user = ? AND F.$counterColumn > 0
            ORDER BY F.title ASC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->num_rows;
        $stmt->close();
        return $count;
    },

    'Articles Query - All (LIMIT 100)' => function() use ($mysqli, $userId) {
        $limit = 100;
        $stmt = $mysqli->prepare("
            SELECT I.id, I.title, I.pubdate, I.author, I.description, I.link, I.id_flux
            FROM reader_unread_cache C
            INNER JOIN reader_item I ON C.id_item = I.id
            WHERE C.id_user = ?
            ORDER BY I.pubdate DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->num_rows;
        $stmt->close();
        return $count;
    },

    'Articles Query - Single Feed (LIMIT 100)' => function() use ($mysqli, $userId) {
        $feedId = 976; // Feed de test
        $limit = 100;
        $stmt = $mysqli->prepare("
            SELECT I.id, I.title, I.pubdate, I.author, I.description, I.link, I.id_flux
            FROM reader_unread_cache C
            INNER JOIN reader_item I ON C.id_item = I.id
            WHERE C.id_user = ? AND C.id_flux = ?
            ORDER BY I.pubdate DESC
            LIMIT ?
        ");
        $stmt->bind_param("iii", $userId, $feedId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->num_rows;
        $stmt->close();
        return $count;
    },

    'Mark Article Read (single)' => function() use ($mysqli, $userId) {
        // Get a random unread article
        $stmt = $mysqli->prepare("
            SELECT id_item FROM reader_unread_cache
            WHERE id_user = ? LIMIT 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) return 0;

        $itemId = $row['id_item'];

        // Mark as read (will trigger triggers)
        $stmt = $mysqli->prepare("
            INSERT IGNORE INTO reader_user_item (id_user, id_item)
            VALUES (?, ?)
        ");
        $stmt->bind_param("ii", $userId, $itemId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        // Mark back as unread for next iteration
        $stmt = $mysqli->prepare("
            DELETE FROM reader_user_item
            WHERE id_user = ? AND id_item = ?
        ");
        $stmt->bind_param("ii", $userId, $itemId);
        $stmt->execute();
        $stmt->close();

        return $affected;
    },

    'Count Unread by User' => function() use ($mysqli, $userId) {
        $stmt = $mysqli->prepare("
            SELECT COUNT(*) as cnt
            FROM reader_unread_cache
            WHERE id_user = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['cnt'];
    }
];

$results = [];

foreach ($benchmarks as $name => $query) {
    echo "Testing: $name\n";

    // Warmup
    for ($i = 0; $i < $warmupIterations; $i++) {
        $query();
    }

    // Actual benchmark
    $times = [];
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        $rowCount = $query();
        $times[] = (microtime(true) - $start) * 1000; // Convert to ms
    }

    // Calculate stats
    sort($times);
    $results[$name] = [
        'min' => $times[0],
        'max' => $times[count($times) - 1],
        'avg' => array_sum($times) / count($times),
        'median' => $times[intval(count($times) / 2)],
        'p95' => $times[intval(count($times) * 0.95)],
        'p99' => $times[intval(count($times) * 0.99)],
        'rows' => $rowCount ?? 'N/A'
    ];

    echo "  ✓ Completed ({$iterations} iterations)\n";
}

// Display results
echo "\n" . str_repeat("═", 64) . "\n";
echo "RESULTS (times in milliseconds)\n";
echo str_repeat("═", 64) . "\n\n";

foreach ($results as $name => $stats) {
    echo "$name\n";
    echo "  Rows returned: {$stats['rows']}\n";
    printf("  Min:    %7.2f ms\n", $stats['min']);
    printf("  Avg:    %7.2f ms\n", $stats['avg']);
    printf("  Median: %7.2f ms\n", $stats['median']);
    printf("  P95:    %7.2f ms\n", $stats['p95']);
    printf("  P99:    %7.2f ms\n", $stats['p99']);
    printf("  Max:    %7.2f ms\n", $stats['max']);
    echo "\n";
}

echo str_repeat("═", 64) . "\n";

// Helper function
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
