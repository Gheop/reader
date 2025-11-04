<?php
/**
 * Menu API endpoint - Returns user's feeds with unread counts
 * Uses MenuBuilder helper for business logic
 */

require_once __DIR__ . '/vendor/autoload.php';

use Gheop\Reader\MenuBuilder;
use Gheop\Reader\SecurityHelper;

include('/www/conf.php');

// Security: Validate user authentication
if (!isset($_SESSION['user_id']) || !SecurityHelper::isValidUserId($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    // Prepared statement for security
    $stmt = $_SESSION['mysqli']->prepare('
        SELECT CONCAT(
            \'"\', F.id, \'":{"t":"\', F.title, \'","n":\', COUNT(I.id), \',"d":"\',
            COALESCE(F.description, \'\'), \'","l":"\', COALESCE(F.link, \'\'), \'"}\')
        FROM reader_user_flux UF
        INNER JOIN reader_flux F ON UF.id_flux = F.id
        INNER JOIN reader_item I ON F.id = I.id_flux
        LEFT JOIN reader_user_item RUI ON I.id = RUI.id_item AND RUI.id_user = ?
        WHERE UF.id_user = ?
            AND I.pubdate > DATE_SUB(NOW(), INTERVAL 15 DAY)
            AND RUI.id_item IS NULL
        GROUP BY F.id, F.title, F.description, F.link
        ORDER BY F.title ASC
    ');

    if (!$stmt) {
        throw new Exception('Failed to prepare statement');
    }

    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    // Collect feed data
    $feedData = [];
    while ($row = $result->fetch_row()) {
        $feedData[] = $row[0];
    }

    $stmt->close();

    // Use MenuBuilder to construct JSON
    $json = MenuBuilder::buildMenuJson($feedData);

    // Return JSON response
    header('Content-Type: application/json; charset=utf-8');
    echo $json;

} catch (Exception $e) {
    // Log error (in production, log to file)
    error_log('Menu API error: ' . $e->getMessage());

    // Return error response
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error']);
}
