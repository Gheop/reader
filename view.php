<?php
/**
 * View API endpoint - Returns articles for user's feeds
 * Returns JSON format: {"article_id": {"t": "title", "p": "pubdate", "a": "author", ...}}
 *
 * Security improvements:
 * - Prepared statements for user_id and feed_id (prevents SQL injection)
 * - Validated LIMIT as string after (int) casting (compatible with old MySQL)
 * - Native json_encode() instead of SQL CONCAT
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
$limit = 50; // Default
if (isset($_POST['nb']) && is_numeric($_POST['nb'])) {
    $limit = min(100, max(1, (int)$_POST['nb'])); // Max 100, min 1
}

// Build LIMIT clause as string (secure because validated with (int))
// Note: Old MySQL versions don't support placeholders in LIMIT
$limitClause = "LIMIT " . $limit;

// Build feed filter with prepared statement
$feedFilter = '';
$hasFeedFilter = false;
$feedId = 0;

if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $feedId = (int)$_POST['id'];
    $feedFilter = 'AND F.id = ?';
    $hasFeedFilter = true;
}

// Prepared statement for security (except LIMIT which is validated string)
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
    FROM reader_item I, reader_flux F, reader_user_flux U
    WHERE U.id_user = ?
        AND U.id_flux = I.id_flux
        AND I.id_flux = F.id
        $feedFilter
        AND I.id NOT IN (
            SELECT id_item
            FROM reader_user_item AS UI
            WHERE UI.id_user = ?
                AND UI.date > (NOW() - INTERVAL 15 DAY)
        )
        AND I.pubdate > (NOW() - INTERVAL 15 DAY)
    ORDER BY I.pubdate DESC
    $limitClause
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log('View prepare failed: ' . $mysqli->error);
    echo '{}';
    exit;
}

// Bind parameters (with or without feed filter)
if ($hasFeedFilter) {
    $stmt->bind_param('iii', $userId, $feedId, $userId);
} else {
    $stmt->bind_param('ii', $userId, $userId);
}

if (!$stmt->execute()) {
    error_log('View execute failed: ' . $stmt->error);
    echo '{}';
    exit;
}

$result = $stmt->get_result();

if (!$result) {
    error_log('View get_result failed: ' . $stmt->error);
    echo '{}';
    exit;
}

// Build response array
$articles = [];
while ($row = $result->fetch_assoc()) {
    $articles[(string)$row['id']] = [
        't' => $row['title'] ?? '',
        'p' => $row['pubdate'] ?? '',
        'd' => $row['description'] ?? '',
        'l' => $row['link'] ?? '',
        'a' => $row['author'] ?? '',
        'f' => $row['id_flux'] ?? '',
        'n' => $row['feed_title'] ?? '',
        'e' => $row['feed_description'] ?? '',
        'o' => $row['feed_link'] ?? ''
    ];
}

$stmt->close();

// Return JSON response
echo json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
