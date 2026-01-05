<?php
/**
 * Get starred articles for current user
 * Returns JSON in same format as api.php articles
 */
include(__DIR__ . '/../config/conf.php');
include(__DIR__ . '/../config/auth.php');

header('Content-Type: application/json; charset=utf-8');

// Security: Validate authentication
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(401);
    echo '{"error":"Unauthorized"}';
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Limit
$limit = 100;
if (isset($_GET['nb']) && is_numeric($_GET['nb'])) {
    $limit = min(200, max(1, (int)$_GET['nb']));
}

// Fetch starred articles with full details
$stmt = $mysqli->prepare("
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
        S.starred_at
    FROM reader_starred_items S
    INNER JOIN reader_item I ON S.id_item = I.id
    INNER JOIN reader_flux F ON I.id_flux = F.id
    WHERE S.id_user = ?
    ORDER BY S.starred_at DESC
    LIMIT ?
");
$stmt->bind_param("ii", $userId, $limit);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    error_log('Starred query failed: ' . $mysqli->error);
    echo '{}';
    exit;
}

// Build JSON (same format as api.php)
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
    $json .= ',"f":"' . ($row['id_flux'] ?? '') . '","n":"' . $feed_title . '","e":"' . $feed_desc . '"';
    $json .= ',"s":1}'; // s=1 means starred
}
$json .= '}';

echo $json;
?>
