<?php
/**
 * View API - Phase 2 Optimized: Utilise reader_unread_cache
 * Performance: 5-10ms au lieu de 200ms+
 */

header("Content-Type: application/json; charset=UTF-8");
include('/www/conf.php');

// Security: Validate user authentication
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    echo '{}';
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Validate and sanitize parameters
$limit = 50;
if (isset($_POST['nb']) && is_numeric($_POST['nb'])) {
    $limit = min(100, max(1, (int)$_POST['nb']));
}

// Build feed filter
$feedFilter = '';
if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $feedId = (int)$_POST['id'];
    $feedFilter = 'AND C.id_flux = '.$feedId;
}

// ============================================================================
// REQUÊTE OPTIMISÉE PHASE 2
// ============================================================================
// Utilise reader_unread_cache au lieu de scanner toute reader_item
// Résultat: Scan de ~3k lignes au lieu de 80k+
// Performance: 5-10ms au lieu de 200ms+

$sql = "
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

$result = $_SESSION['mysqli']->query($sql);

if (!$result) {
    error_log('View query failed: ' . $_SESSION['mysqli']->error);
    echo '{}';
    exit;
}

// Build JSON with simple string concatenation (fastest)
$json = '{';
$first = true;
while ($row = $result->fetch_assoc()) {
    if (!$first) $json .= ',';
    $first = false;

    // Escape quotes and backslashes
    $title = str_replace(['\\', '"'], ['\\\\', '\\"'], $row['title'] ?? '');
    $desc = str_replace(['\\', '"'], ['\\\\', '\\"'], $row['description'] ?? '');
    $author = str_replace(['\\', '"'], ['\\\\', '\\"'], $row['author'] ?? '');
    $feed_title = str_replace(['\\', '"'], ['\\\\', '\\"'], $row['feed_title'] ?? '');
    $feed_desc = str_replace(['\\', '"'], ['\\\\', '\\"'], $row['feed_description'] ?? '');

    $json .= '"' . $row['id'] . '":{"t":"' . $title . '","p":"' . ($row['pubdate'] ?? '') . '"';

    if (!empty($row['author'])) {
        $json .= ',"a":"' . $author . '"';
    }

    $json .= ',"d":"' . $desc . '","l":"' . ($row['link'] ?? '') . '","o":"' . ($row['feed_link'] ?? '') . '"';
    $json .= ',"f":"' . ($row['id_flux'] ?? '') . '","n":"' . $feed_title . '","e":"' . $feed_desc . '"}';
}
$json .= '}';

echo $json;
?>
