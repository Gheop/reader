<?php
/**
 * View API endpoint - Returns articles for user's feeds
 * Returns JSON format: {"article_id": {"t": "title", "p": "pubdate", "a": "author", ...}}
 */

require_once __DIR__ . '/vendor/autoload.php';

use Gheop\Reader\ViewHelper;
use Gheop\Reader\SecurityHelper;

header("Content-Type: application/json; charset=UTF-8");
include('/www/conf.php');

// Security: Validate user authentication
if (!isset($_SESSION['user_id']) || !SecurityHelper::isValidUserId($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    // Sanitize and validate parameters
    $params = ViewHelper::sanitizeParams([
        'nb' => $_POST['nb'] ?? null,
        'id' => $_POST['id'] ?? null,
        'offset' => $_POST['offset'] ?? null
    ]);

    // Build SQL clauses
    $limitClause = ViewHelper::buildLimitClause($params['nb'], $params['offset']);
    $feedFilter = ViewHelper::buildFeedFilter($params['id']);

    // Prepared statement for security
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
        FROM reader_user_flux U
        INNER JOIN reader_item I ON U.id_flux = I.id_flux
        INNER JOIN reader_flux F ON I.id_flux = F.id
        LEFT JOIN reader_user_item UI ON I.id = UI.id_item
            AND UI.id_user = ?
            AND UI.date > DATE_SUB(NOW(), INTERVAL 15 DAY)
        WHERE U.id_user = ?
            AND I.pubdate > DATE_SUB(NOW(), INTERVAL 15 DAY)
            AND UI.id_item IS NULL
            $feedFilter
        ORDER BY I.pubdate DESC
        LIMIT $limitClause
    ";

    $stmt = $_SESSION['mysqli']->prepare($sql);

    if (!$stmt) {
        throw new Exception('Failed to prepare statement');
    }

    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    // Build response array
    $articles = [];
    while ($row = $result->fetch_assoc()) {
        $articles[(string)$row['id']] = ViewHelper::formatArticle([
            't' => $row['title'],
            'p' => $row['pubdate'],
            'd' => $row['description'] ?? '',
            'l' => $row['link'] ?? '',
            'a' => $row['author'] ?? '',
            'f' => $row['id_flux'],
            'n' => $row['feed_title'] ?? '',
            'e' => $row['feed_description'] ?? '',
            'o' => $row['feed_link'] ?? ''
        ]);
    }

    $stmt->close();

    // Return JSON response
    echo json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    // Log error (in production, log to file)
    error_log('View API error: ' . $e->getMessage());

    // Return error response
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
