<?php
/**
 * Parallel RSS Feed Update Script
 * High-performance feed fetching with batch processing and concurrency control
 *
 * Features:
 * - Batch processing: processes feeds in chunks to avoid memory overflow
 * - Concurrency control: limits parallel downloads to prevent server overload
 * - Priority ordering: updates oldest feeds first
 * - Progress tracking: real-time feedback on processing
 * - Error resilience: continues on failures
 */

include(__DIR__ . '/../../config/conf.php');
include(__DIR__ . '/../../clean_text.php');

// Query performance monitoring helper
function logSlowQuery($queryName, $duration, $threshold = 100) {
    if ($duration > $threshold) {
        error_log(sprintf("SLOW QUERY [%s]: %.2fms (threshold: %dms)", $queryName, $duration, $threshold));
    }
}

// Configuration
$BATCH_SIZE = 50;           // Process 50 feeds per batch
$MAX_CONCURRENT = 20;       // Max 20 parallel downloads at once
$TIMEOUT = 30;              // 30 seconds timeout per feed
$CONNECT_TIMEOUT = 10;      // 10 seconds connection timeout

// Allow CLI execution for cron jobs
if (php_sapi_name() === 'cli') {
    if (!isset($_SESSION)) {
        session_start();
    }
    $_SESSION['user_id'] = 1;
    if (!isset($mysqli)) {
        $mysqli = $mysqli;
    }
}

// Security: Check authentication
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(403);
    die('Unauthorized access');
}

ini_set('max_execution_time', '900'); // 15 minutes max
$mysqli = $mysqli;

// Release session lock
session_write_close();

// Parse parameters
$feedId = null;
$limit = null;
if(isset($_GET['id']) && is_numeric($_GET['id'])) {
    $feedId = (int)$_GET['id'];
}
if(isset($_GET['limit']) && is_numeric($_GET['limit'])) {
    $limit = min(500, max(1, (int)$_GET['limit']));
}

$DEBUG = isset($_GET['debug']) ? 1 : 0;
$startTime = microtime(true);

// Statistics
$stats = [
    'total' => 0,
    'processed' => 0,
    'new_articles' => 0,
    'errors' => 0,
    'batches' => 0
];

echo "=== Parallel Feed Update ===\n";
echo "Batch size: $BATCH_SIZE | Concurrency: $MAX_CONCURRENT\n\n";

/**
 * Complete relative URLs with base URL
 */
function complete_link($link, $linkmaster) {
    if(isset($link) && !preg_match('/^https?:\/\//', $link)) {
        if(substr($link, 0, 1) == '/') {
            $pu = parse_url($linkmaster);
            if(!isset($pu['scheme'])) $pu['scheme'] = 'https';
            if(substr($link, 1, 1) == '/') {
                $link = $pu['scheme'] . ':' . $link;
            } else {
                $link = $pu['scheme'] . '://' . $pu['host'] . $link;
            }
        } else {
            $link = rtrim($linkmaster, '/') . '/' . $link;
        }
    }
    return $link;
}

/**
 * Fetch YouTube description (async-ready)
 */
function get_youtube_description($videoId) {
    if (!defined('YOUTUBE_API_KEY') || empty(YOUTUBE_API_KEY)) {
        return null;
    }

    $url = 'https://www.googleapis.com/youtube/v3/videos?part=snippet&id=' . urlencode($videoId) . '&key=' . urlencode(YOUTUBE_API_KEY);

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200 || !$response) {
        return null;
    }

    $data = json_decode($response, true);

    if (isset($data['items'][0]['snippet']['description'])) {
        $description = trim($data['items'][0]['snippet']['description']);
        if (strlen($description) > 2000) {
            $description = substr($description, 0, 2000) . '...';
        }
        return $description;
    }

    return null;
}

/**
 * Process a batch of feeds in parallel
 */
function process_batch($mysqli, $feeds, $maxConcurrent, $timeout, $connectTimeout, $debug) {
    global $stats;

    $batchStats = [
        'processed' => 0,
        'new_articles' => 0,
        'errors' => 0
    ];

    // Prepare all metadata in one query
    $feedIds = array_column($feeds, 'id');
    $feedIdList = implode(',', array_map('intval', $feedIds));
    $metaQuery = "SELECT id, link, title, rss FROM reader_flux WHERE id IN ($feedIdList)";
    $query_start = microtime(true);
    $metaResult = $mysqli->query($metaQuery);
    logSlowQuery('up_parallel.php - batch feed metadata', (microtime(true) - $query_start) * 1000);

    $feedMeta = [];
    while($row = $metaResult->fetch_assoc()) {
        $feedMeta[$row['id']] = $row;
    }

    // Initialize curl multi handle with concurrency limit
    $mh = curl_multi_init();
    curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, $maxConcurrent);

    $handles = [];
    $handleMap = [];

    // Add all feeds to multi handle
    foreach($feeds as $feed) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $feed['rss'],
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => 'UTF-8',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);

        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
        $handleMap[(int)$ch] = [
            'feed_id' => $feed['id'],
            'url_origin' => $feed['rss']
        ];
    }

    // Execute all requests in parallel
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 0.1);
        }
    } while ($running > 0);

    // Process results
    foreach($handles as $ch) {
        $feedInfo = $handleMap[(int)$ch];
        $feedId = $feedInfo['feed_id'];
        $urlOrigin = $feedInfo['url_origin'];

        if(!isset($feedMeta[$feedId])) {
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            $batchStats['errors']++;
            continue;
        }

        $meta = $feedMeta[$feedId];
        $xml = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        echo sprintf("Feed %d: %s... ", $feedId, substr($meta['title'], 0, 30));

        // Check for errors
        if($httpCode != 200 || empty($xml)) {
            echo "HTTP $httpCode\n";
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            $batchStats['errors']++;
            continue;
        }

        // Update RSS URL if redirected
        if($urlOrigin != $effectiveUrl) {
            $stmt = $mysqli->prepare("UPDATE reader_flux SET rss = ? WHERE id = ?");
            $stmt->bind_param("si", $effectiveUrl, $feedId);
            $stmt->execute();
        }

        // Parse XML
        $xml = trim($xml);
        $xml = preg_replace('/^(.*<\/rss>).*$/s', '\\1', $xml);
        $xml = preg_replace('/url="(.*?\.(jpg|png|gif))\?.*?"/s', 'url="' . htmlspecialchars('\\1', ENT_XML1, 'UTF-8', true) . '"', $xml);
        $xml = preg_replace('/type=""/s', '', $xml);

        $rss = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        if(empty($rss)) {
            echo "Empty/Invalid\n";
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            $batchStats['errors']++;
            continue;
        }

        // Extract feed structure
        if(isset($rss->channel->item)) {
            $items = $rss->channel->item;
            $linkmaster = $rss->channel->link ?? $meta['link'];
        } elseif(isset($rss->item)) {
            $items = $rss->item;
            $linkmaster = $rss->link ?? $meta['link'];
        } elseif(isset($rss->entry)) {
            $items = $rss->entry;
            $linkmaster = $rss->link[0]['href'] ?? $meta['link'];
        } else {
            echo "Unknown format\n";
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            $batchStats['errors']++;
            continue;
        }

        $newArticles = 0;
        $itemCount = 0;

        // Process items (max 5 per feed)
        foreach($items as $item) {
            if($itemCount++ >= 5) break;

            // Extract link
            $link = null;
            if(is_object($item->link)) {
                foreach($item->link as $l) {
                    if($l['rel'] == "alternate" || $l['rel'] == "self") {
                        $link = $l['href'];
                        break;
                    }
                    if(!isset($link) && isset($l['href'])) {
                        $link = $l['href'];
                    }
                }
            }

            if(!isset($link) && isset($item->guid) && preg_match('/^https?:\/\//', $item->guid)) {
                $link = $item->guid;
            }
            if(!isset($link) && isset($item->link) && preg_match('/^https?:\/\//', $item->link)) {
                $link = $item->link;
            }

            $link = complete_link($link, $linkmaster);

            if(!isset($link) || empty($link)) {
                continue;
            }

            // Clean link
            $link = str_replace([')', '(', '"', '\\'], ['', '', '', '\\\\'], $link);

            // Extract GUID first (before checking existence)
            $guid = isset($item->guid) && $item->guid ? clean_txt($item->guid) : null;

            // Generate synthetic GUID if none provided (for feeds without proper GUIDs)
            // This prevents re-importing old articles after archiving
            // NOTE: Don't use title in hash (YouTube A/B testing changes titles)
            if (!$guid || trim($guid) === '') {
                $guid = 'synthetic-' . md5($feedId . '|' . $link);
            }

            // Check if article exists (OPTIMIZED: exact match instead of LIKE, ~17x faster)
            $query_start = microtime(true);
            $stmt = $mysqli->prepare("
                SELECT 1 FROM reader_item WHERE id_flux = ? AND (guid = ? OR link = ?)
                UNION
                SELECT 1 FROM reader_item_archive WHERE id_flux = ? AND (guid = ? OR link = ?)
                LIMIT 1
            ");
            if (!$stmt) {
                error_log("Prepare failed for feed $feedId: " . $mysqli->error);
                continue;
            }
            $stmt->bind_param("ississ", $feedId, $guid, $link, $feedId, $guid, $link);
            $stmt->execute();
            $existingResult = $stmt->get_result();
            $stmt->close();
            logSlowQuery('up_parallel.php - check existing article', (microtime(true) - $query_start) * 1000, 50);

            if ($existingResult->num_rows > 0) {
                continue; // Article already exists (in main table or archive)
            }

            // Extract article data
            $title = isset($item->title) ? $item->title : null;
            if(!$title) continue;

            // Date
            $iDate = $item->pubDate ?? $item->published ?? null;
            try {
                $date = new DateTime($iDate);
            } catch (Exception $e) {
                $date = new DateTime();
            }
            $iDate = $date->getTimestamp();
            if(!isset($iDate) || $iDate > time()) {
                $iDate = time();
            }

            // Skip articles older than 30 days (avoid importing ancient articles from feeds)
            $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
            if($iDate < $thirtyDaysAgo) {
                continue; // Skip old article
            }

            // Content
            $content = $item->description ?? $item->content ?? $item->summary ?? '';

            // YouTube handling
            $youtubeVideoId = null;
            if(preg_match('/^(.*\/\/)?(www\.)?youtube\.com\/(watch\?v=|shorts\/)([^&\s]+)/', $link, $m)) {
                $youtubeVideoId = $m[4];
            } elseif(preg_match('/^(.*\/\/)?youtu\.be\/([^&\s]+)/', $link, $m)) {
                $youtubeVideoId = $m[2];
            }

            if($youtubeVideoId) {
                // Check if we already have this video in DB (description already fetched)
                $ytDescription = null;
                $needsFetch = true;

                $checkStmt = $mysqli->prepare("SELECT description FROM reader_item WHERE link = ? LIMIT 1");
                $checkStmt->bind_param("s", $link);
                $query_start = microtime(true);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                logSlowQuery('up_parallel.php - check YouTube cache', (microtime(true) - $query_start) * 1000, 50);
                if ($checkRow = $checkResult->fetch_assoc()) {
                    // Video already exists, description already in content
                    $needsFetch = false;
                    $content = $checkRow['description']; // Use existing content with description
                }

                // Only fetch from API for new videos
                if ($needsFetch) {
                    $ytDescription = get_youtube_description($youtubeVideoId);
                }

                $videoContent = '<yt>' . $youtubeVideoId . '</yt>';
                if($ytDescription) {
                    $videoContent .= '<div class="yt-description">' . nl2br(htmlspecialchars($ytDescription)) . '</div>';
                }

                // Only build videoContent for new items
                if ($needsFetch) {
                    $content = !empty($content) ? $videoContent . '<hr />' . $content : $videoContent;
                }
            } elseif(preg_match('/^(\/\/.*\.(jpe?g|gif|png))/', $link, $m)) {
                $content = '<img loading="lazy" decoding="async" src="' . htmlspecialchars($m[1]) . '" />';
            }

            // Author
            $author = $item->author->name ?? $item->author ?? '';

            // Clean text
            $title = clean_txt($title);
            $content = clean_txt($content);
            $author = clean_txt($author);

            // Use link as GUID fallback if not set earlier
            if(!$guid) {
                $guid = $link;
            }

            // Insert article (YouTube description already concatenated in $content)
            $stmt = $mysqli->prepare("
                INSERT INTO reader_item (id, id_flux, pubdate, guid, title, author, link, description)
                VALUES ('', ?, ?, ?, ?, ?, ?, ?)
            ");
            $pubdate = date("Y-m-d H:i:s", $iDate);
            $stmt->bind_param("issssss", $feedId, $pubdate, $guid, $title, $author, $link, $content);

            $query_start = microtime(true);
            if($stmt->execute()) {
                logSlowQuery('up_parallel.php - insert article', (microtime(true) - $query_start) * 1000, 50);
                $newArticles++;

                // Manual cache/counter management (triggers disabled)
                $newArticleId = $mysqli->insert_id;

                // Add to reader_unread_cache for all users subscribed to this feed
                $cacheStmt = $mysqli->prepare("
                    INSERT INTO reader_unread_cache (id_user, id_flux, id_item, pubdate)
                    SELECT UF.id_user, ?, ?, ?
                    FROM reader_user_flux UF
                    WHERE UF.id_flux = ?
                ");
                $cacheStmt->bind_param("iisi", $feedId, $newArticleId, $pubdate, $feedId);
                $cacheStmt->execute();

                // Update counters in reader_flux_user_stats
                // First ensure rows exist, then increment
                $counterStmt = $mysqli->prepare("
                    INSERT INTO reader_flux_user_stats (id_user, id_flux, unread_count)
                    SELECT UF.id_user, ?, 1
                    FROM reader_user_flux UF
                    WHERE UF.id_flux = ?
                    ON DUPLICATE KEY UPDATE unread_count = unread_count + 1
                ");
                $counterStmt->bind_param("ii", $feedId, $feedId);
                $counterStmt->execute();
            }
        }

        // Update feed timestamp
        $stmt = $mysqli->prepare("UPDATE reader_flux SET `update` = CURRENT_TIMESTAMP() WHERE id = ?");
        $stmt->bind_param("i", $feedId);
        $stmt->execute();

        echo "+$newArticles\n";
        $batchStats['new_articles'] += $newArticles;
        $batchStats['processed']++;

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }

    curl_multi_close($mh);

    return $batchStats;
}

// Build query
$where = [];
$params = [];
$types = '';

if($feedId !== null) {
    $where[] = 'F.id = ?';
    $params[] = $feedId;
    $types .= 'i';
}

$whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
$limitClause = $limit !== null ? ' LIMIT ' . $limit : '';

// Count total feeds
$countQuery = "SELECT COUNT(*) as total FROM reader_flux F" . $whereClause;
if(!empty($params)) {
    $stmt = $mysqli->prepare($countQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $mysqli->query($countQuery);
}
$stats['total'] = $result->fetch_assoc()['total'];

echo "Total feeds to update: {$stats['total']}\n\n";

// Process in batches
$offset = 0;

while(true) {
    // Fetch batch - prioritize oldest feeds
    $batchQuery = "
        SELECT F.id, F.rss, F.title
        FROM reader_flux F
        $whereClause
        ORDER BY F.update ASC
        LIMIT $BATCH_SIZE OFFSET $offset
    ";

    if(!empty($params)) {
        $stmt = $mysqli->prepare($batchQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $mysqli->query($batchQuery);
    }

    $feeds = [];
    while($row = $result->fetch_assoc()) {
        $feeds[] = $row;
    }

    if(empty($feeds)) {
        break; // No more feeds
    }

    $stats['batches']++;
    echo "--- Batch {$stats['batches']} (" . count($feeds) . " feeds) ---\n";

    $batchStats = process_batch($mysqli, $feeds, $MAX_CONCURRENT, $TIMEOUT, $CONNECT_TIMEOUT, $DEBUG);

    $stats['processed'] += $batchStats['processed'];
    $stats['new_articles'] += $batchStats['new_articles'];
    $stats['errors'] += $batchStats['errors'];

    echo "Batch complete: {$batchStats['processed']} processed, {$batchStats['new_articles']} new articles, {$batchStats['errors']} errors\n\n";

    $offset += $BATCH_SIZE;

    // Memory cleanup
    gc_collect_cycles();
}

$duration = round(microtime(true) - $startTime, 2);

echo "\n=== Update Complete ===\n";
echo "Duration: {$duration}s\n";
echo "Total feeds: {$stats['total']}\n";
echo "Processed: {$stats['processed']}\n";
echo "New articles: {$stats['new_articles']}\n";
echo "Errors: {$stats['errors']}\n";
echo "Batches: {$stats['batches']}\n";
echo "Avg speed: " . round($stats['processed'] / max(1, $duration), 2) . " feeds/sec\n";
?>
