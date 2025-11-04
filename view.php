<?php
/**
 * View API endpoint - Returns articles for user's feeds
 * Returns JSON format: {"article_id": {"t": "title", "p": "pubdate", "a": "author", ...}}
 */

include('/www/conf.php');

// Security: Validate user authentication
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    // Validate and sanitize parameters
    $limit = 50; // Default limit
    $offset = 0; // Default offset

    if (isset($_POST['nb']) && is_numeric($_POST['nb'])) {
        $limit = min(100, max(1, (int)$_POST['nb'])); // Max 100, min 1
    }

    if (isset($_POST['offset']) && is_numeric($_POST['offset'])) {
        $offset = max(0, (int)$_POST['offset']);
    }

    // Build feed filter
    $feedFilter = '';
    $bindTypes = 'ii';
    $bindParams = [$userId, $userId];

    if (isset($_POST['id']) && is_numeric($_POST['id'])) {
        $feedId = (int)$_POST['id'];
        $feedFilter = 'AND F.id = ?';
        $bindTypes = 'iii';
        $bindParams[] = $feedId;
    }

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
        LIMIT ?, ?
    ";

    $stmt = $_SESSION['mysqli']->prepare($sql);

    if (!$stmt) {
        throw new Exception('Failed to prepare statement');
    }

    // Bind parameters
    if ($feedFilter) {
        $stmt->bind_param($bindTypes . 'ii', ...$bindParams, $offset, $limit);
    } else {
        $stmt->bind_param($bindTypes . 'ii', ...$bindParams, $offset, $limit);
    }

    $stmt->execute();
    $result = $stmt->get_result();

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
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    // Log error (in production, log to file)
    error_log('View API error: ' . $e->getMessage());

    // Return error response
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error']);
}
