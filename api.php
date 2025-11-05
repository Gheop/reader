<?php
/**
 * Combined API - Menu + Articles
 * Optimized for localStorage caching with single request
 * Returns both menu counts and articles in one response
 */

$start_time = microtime(true);
header("Content-Type: application/json; charset=UTF-8");
include('/www/conf.php');

// Security: Validate user authentication
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(401);
    echo '{"error":"Unauthorized"}';
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ============================================================================
// PARAMETERS
// ============================================================================

// Article limit
$limit = 50;
if (isset($_GET['nb']) && is_numeric($_GET['nb'])) {
    $limit = min(100, max(1, (int)$_GET['nb']));
}

// Feed filter for articles
$feedFilter = '';
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $feedId = (int)$_GET['id'];
    $feedFilter = 'AND C.id_flux = '.$feedId;
}

// ============================================================================
// QUERY 1: MENU DATA (with unread counts)
// ============================================================================

$counterColumn = $userId == 1 ? 'unread_count_user_1' : 'unread_count_user_2';

$menuSql = "
    SELECT
        F.id,
        F.title,
        F.$counterColumn as n,
        F.description,
        F.link
    FROM reader_flux F
    INNER JOIN reader_user_flux UF ON UF.id_flux = F.id
    WHERE UF.id_user = $userId
        AND F.$counterColumn > 0
    ORDER BY F.title ASC
";

$menuResult = $_SESSION['mysqli']->query($menuSql);

if (!$menuResult) {
    error_log('Menu query failed: ' . $_SESSION['mysqli']->error);
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
    WHERE C.id_user = $userId
        $feedFilter
    ORDER BY I.pubdate DESC
    LIMIT $limit
";

$articlesResult = $_SESSION['mysqli']->query($articlesSql);

if (!$articlesResult) {
    error_log('Articles query failed: ' . $_SESSION['mysqli']->error);
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
    $articlesJson .= ',"f":"' . ($row['id_flux'] ?? '') . '","n":"' . $feed_title . '","e":"' . $feed_desc . '"}';
}
$articlesJson .= '}';

// ============================================================================
// COMBINED RESPONSE
// ============================================================================

$execution_time = round((microtime(true) - $start_time) * 1000, 2);
header('X-Execution-Time: ' . $execution_time . 'ms');

echo '{"menu":' . $menuJson . ',"articles":' . $articlesJson . ',"timestamp":' . time() . '}';
?>
