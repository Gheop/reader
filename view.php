<?php
/**
 * View API endpoint - Returns articles for user's feeds
 * Returns JSON format: {"article_id": {"t": "title", "p": "pubdate", "a": "author", ...}}
 *
 * Security improvements:
 * - Prepared statements for user_id and feed_id (prevents SQL injection)
 * - Validated LIMIT as string after (int) casting (compatible with old MySQL)
 *
 * Note: JSON is built manually (not with json_encode) because descriptions in DB
 * are already escaped for JSON by clean_text.php (\" for quotes, \\ for backslashes)
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

// Build JSON manually (like MySQL CONCAT did in original)
// Descriptions in DB are already escaped for JSON by clean_text.php
$json = '{';
$first = true;

while ($row = $result->fetch_assoc()) {
    if (!$first) $json .= ',';
    $first = false;

    // Article ID as key
    $json .= '"' . $row['id'] . '":{';

    // Title (needs escaping)
    $json .= '"t":"' . str_replace(['"', '\\'], ['\"', '\\\\'], $row['title'] ?? '') . '"';

    // Pubdate
    $json .= ',"p":"' . ($row['pubdate'] ?? '') . '"';

    // Author (conditional, needs escaping)
    if (!empty($row['author'])) {
        $json .= ',"a":"' . str_replace(['"', '\\'], ['\"', '\\\\'], $row['author']) . '"';
    }

    // Description (already escaped in DB by clean_text.php)
    $json .= ',"d":"' . ($row['description'] ?? '') . '"';

    // Link
    $json .= ',"l":"' . ($row['link'] ?? '') . '"';

    // Feed link
    $json .= ',"o":"' . ($row['feed_link'] ?? '') . '"';

    // Feed ID
    $json .= ',"f":"' . ($row['id_flux'] ?? '') . '"';

    // Feed title (needs escaping)
    $json .= ',"n":"' . str_replace(['"', '\\'], ['\"', '\\\\'], $row['feed_title'] ?? '') . '"';

    // Feed description (needs escaping)
    $json .= ',"e":"' . str_replace(['"', '\\'], ['\"', '\\\\'], $row['feed_description'] ?? '') . '"';

    $json .= '}';
}

$json .= '}';

$stmt->close();

// Return JSON response
echo $json;
?>
