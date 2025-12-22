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
        // Remove read mark
        $stmt = $mysqli->prepare("DELETE FROM reader_user_item WHERE id_user = ? AND id_item = ?");
        $stmt->bind_param("ii", $userId, $itemId);
        $stmt->execute();
        $stmt->close();

        // Add to unread cache
        $stmt = $mysqli->prepare("INSERT IGNORE INTO reader_unread_cache (id_user, id_item) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $itemId);
        $stmt->execute();
        $stmt->close();

        // Increment feed counter in reader_flux_user_stats
        $stmt = $mysqli->prepare("
            UPDATE reader_flux_user_stats S
            INNER JOIN reader_item I ON I.id = ? AND I.id_flux = S.id_flux
            SET S.unread_count = S.unread_count + 1
            WHERE S.id_user = ?
        ");
        $stmt->bind_param("ii", $itemId, $userId);
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