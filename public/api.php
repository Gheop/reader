<?php
/**
 * Combined API - Menu + Articles
 * Optimized for localStorage caching with single request
 * Returns both menu counts and articles in one response
 */

$start_time = microtime(true);
header("Content-Type: application/json; charset=UTF-8");
include(__DIR__ . '/../config/conf.php');
include(__DIR__ . '/../config/auth.php');

// Query performance monitoring helper
function logSlowQuery($queryName, $duration, $threshold = 100) {
    if ($duration > $threshold) {
        error_log(sprintf("SLOW QUERY [%s]: %.2fms (threshold: %dms)", $queryName, $duration, $threshold));
    }
}

// Security: Validate user authentication
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(401);
    echo '{"error":"Unauthorized"}';
    exit;
}

$userId = (int)$_SESSION['user_id'];
global $mysqli;

// ============================================================================
// PARAMETERS
// ============================================================================

// Article limit
$limit = 100;
if (isset($_GET['nb']) && is_numeric($_GET['nb'])) {
    $limit = min(200, max(1, (int)$_GET['nb']));
}

// Feed filter for articles
$feedId = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $feedId = (int)$_GET['id'];
}

// ============================================================================
// QUERY 1: MENU DATA (with unread counts)
// ============================================================================

// Use reader_flux_user_stats table for user-specific unread counts
$menuSql = "
    SELECT
        F.id,
        F.title,
        S.unread_count as n,
        F.description,
        F.link
    FROM reader_flux F
    INNER JOIN reader_user_flux UF ON UF.id_flux = F.id
    INNER JOIN reader_flux_user_stats S ON S.id_flux = F.id AND S.id_user = ?
    WHERE UF.id_user = ?
        AND S.unread_count > 0
    ORDER BY F.title ASC
";

$stmt = $mysqli->prepare($menuSql);
$stmt->bind_param("ii", $userId, $userId);
$query_start = microtime(true);
$stmt->execute();
$menuResult = $stmt->get_result();
logSlowQuery('api.php - menu query', (microtime(true) - $query_start) * 1000);

if (!$menuResult) {
    error_log('Menu query failed: ' . $mysqli->error);
    http_response_code(500);
    echo '{"error":"Database error"}';
    exit;
}

// Build menu JSON
$menuJson = '{';
$first = true;
while($d = $menuResult->fetch_assoc()) {
    if (!$first) $menuJson .= ',';
    $first = false;

    // Escape only quotes and backslashes
    $title = str_replace(['\\', '"'], ['\\\\', '\\"'], $d['title']);
    $desc = str_replace(['\\', '"'], ['\\\\', '\\"'], $d['description'] ?? '');
    $link = $d['link'] ?? '';

    $menuJson .= '"' . $d['id'] . '":{"t":"' . $title . '","n":' . $d['n'] . ',"d":"' . $desc . '","l":"' . $link . '"}';
}
$menuJson .= '}';

// ============================================================================
// QUERY 2: ARTICLES DATA (unread items)
// ============================================================================

// Build query dynamically based on feed filter
if ($feedId !== null) {
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
            F.link as feed_link,
            CASE WHEN S.id IS NOT NULL THEN 1 ELSE 0 END as starred
        FROM reader_unread_cache C
        INNER JOIN reader_item I ON C.id_item = I.id
        INNER JOIN reader_flux F ON I.id_flux = F.id
        LEFT JOIN reader_starred_items S ON S.id_item = I.id AND S.id_user = C.id_user
        WHERE C.id_user = ? AND C.id_flux = ?
        ORDER BY I.pubdate DESC
        LIMIT ?
    ";

    $stmt = $mysqli->prepare($articlesSql);
    $stmt->bind_param("iii", $userId, $feedId, $limit);
} else {
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
            F.link as feed_link,
            CASE WHEN S.id IS NOT NULL THEN 1 ELSE 0 END as starred
        FROM reader_unread_cache C
        INNER JOIN reader_item I ON C.id_item = I.id
        INNER JOIN reader_flux F ON I.id_flux = F.id
        LEFT JOIN reader_starred_items S ON S.id_item = I.id AND S.id_user = C.id_user
        WHERE C.id_user = ?
        ORDER BY I.pubdate DESC
        LIMIT ?
    ";

    $stmt = $mysqli->prepare($articlesSql);
    $stmt->bind_param("ii", $userId, $limit);
}

$query_start = microtime(true);
$stmt->execute();
$articlesResult = $stmt->get_result();
logSlowQuery('api.php - articles query', (microtime(true) - $query_start) * 1000);

if (!$articlesResult) {
    error_log('Articles query failed: ' . $mysqli->error);
    http_response_code(500);
    echo '{"error":"Database error"}';
    exit;
}

// Build articles JSON
$articlesJson = '{';
$first = true;
while ($row = $articlesResult->fetch_assoc()) {
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
    $articlesJson .= ',"f":"' . ($row['id_flux'] ?? '') . '","n":"' . $feed_title . '","e":"' . $feed_desc . '"';
    $articlesJson .= ',"s":' . ($row['starred'] ?? 0) . '}';
}
$articlesJson .= '}';

// ============================================================================
// COMBINED RESPONSE
// ============================================================================

$execution_time = round((microtime(true) - $start_time) * 1000, 2);
header('X-Execution-Time: ' . $execution_time . 'ms');

echo '{"menu":' . $menuJson . ',"articles":' . $articlesJson . ',"timestamp":' . time() . '}';
?>
