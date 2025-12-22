<?php
/**
 * Mark Article as Unread
 * Security: Uses prepared statements to prevent SQL injection
 */
include(__DIR__ . '/../config/conf.php');
include(__DIR__ . '/../config/auth.php');

// Security: Validate authentication and input
if(!isset($_POST['id']) || !is_numeric($_POST['id']) || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(400);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$itemId = (int)$_POST['id'];

// Start transaction
$mysqli->begin_transaction();

try {
    // Check if article was actually read before unmarking
    $stmt = $mysqli->prepare("SELECT 1 FROM reader_user_item WHERE id_user = ? AND id_item = ?");
    $stmt->bind_param("ii", $userId, $itemId);
    $stmt->execute();
    $wasRead = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($wasRead) {
        // Get article info (id_flux, pubdate) for cache
        $stmt = $mysqli->prepare("SELECT id_flux, pubdate FROM reader_item WHERE id = ?");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();

        if (!$item) {
            throw new Exception("Article not found");
        }

        $feedId = $item['id_flux'];
        $pubdate = $item['pubdate'];

        // Remove read mark
        $stmt = $mysqli->prepare("DELETE FROM reader_user_item WHERE id_user = ? AND id_item = ?");
        $stmt->bind_param("ii", $userId, $itemId);
        $stmt->execute();
        $stmt->close();

        // Add to unread cache (with id_flux and pubdate)
        $stmt = $mysqli->prepare("INSERT IGNORE INTO reader_unread_cache (id_user, id_flux, id_item, pubdate) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $userId, $feedId, $itemId, $pubdate);
        $stmt->execute();
        $stmt->close();

        // Increment feed counter in reader_flux_user_stats
        $stmt = $mysqli->prepare("
            UPDATE reader_flux_user_stats S
            SET S.unread_count = S.unread_count + 1
            WHERE S.id_user = ? AND S.id_flux = ?
        ");
        $stmt->bind_param("ii", $userId, $feedId);
        $stmt->execute();
        $stmt->close();
    }

    $mysqli->commit();

    header('Content-Type: application/json');
    echo '{"unread":true}';

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Unread failed: " . $e->getMessage());
    http_response_code(500);
    echo '{"error":"Database error"}';
}
?>