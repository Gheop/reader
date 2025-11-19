<?php
/**
 * Mark Article(s) as Read
 * Supports batch operations via 'ids' parameter (comma-separated)
 * Security: Uses prepared statements to prevent SQL injection
 */
include('/www/conf.php');
include(__DIR__ . '/auth.php');

// Security: Validate authentication
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Check if batch mode (multiple IDs) or single ID
if(isset($_POST['ids']) && !empty($_POST['ids'])) {
    // Batch mode: comma-separated IDs
    $idsString = $_POST['ids'];
    $ids = array_map('intval', explode(',', $idsString));
    $ids = array_filter($ids, function($id) { return $id > 0; }); // Remove invalid IDs

    if(empty($ids)) {
        http_response_code(400);
        echo '{"error":"No valid IDs"}';
        exit;
    }

    // Use batch insert
    $values = [];
    foreach($ids as $itemId) {
        $values[] = "($userId, $itemId, NOW())";
    }
    $valuesString = implode(',', $values);

    // Start transaction
    $mysqli->begin_transaction();

    try {
        // Mark as read
        $query = "INSERT IGNORE INTO reader_user_item (id_user, id_item, date) VALUES $valuesString";
        $result = $mysqli->query($query);

        if (!$result) {
            throw new Exception("Insert failed: " . $mysqli->error);
        }

        // Remove from unread cache
        $idsString = implode(',', $ids);
        $deleteQuery = "DELETE FROM reader_unread_cache WHERE id_user = $userId AND id_item IN ($idsString)";
        $deleteResult = $mysqli->query($deleteQuery);

        if (!$deleteResult) {
            throw new Exception("Cache delete failed: " . $mysqli->error);
        }

        // Update feed counter
        $updateQuery = "UPDATE reader_flux SET unread_count_user_$userId = (
            SELECT COUNT(*) FROM reader_unread_cache WHERE id_user = $userId AND id_flux = reader_flux.id
        ) WHERE id IN (SELECT DISTINCT id_flux FROM reader_item WHERE id IN ($idsString))";
        $updateResult = $mysqli->query($updateQuery);

        if (!$updateResult) {
            throw new Exception("Counter update failed: " . $mysqli->error);
        }

        $mysqli->commit();

        header('Content-Type: application/json');
        echo '{"read":true,"count":' . count($ids) . '}';

    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Batch read failed: " . $e->getMessage());
        http_response_code(500);
        echo '{"error":"Database error","details":"' . $e->getMessage() . '"}';
    }

} elseif(isset($_POST['id']) && is_numeric($_POST['id'])) {
    // Single ID mode (backwards compatibility)
    $itemId = (int)$_POST['id'];

    $stmt = $mysqli->prepare("INSERT IGNORE INTO reader_user_item (id_user, id_item, date) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $userId, $itemId);
    $stmt->execute();
    $stmt->close();

    header('Content-Type: application/json');
    echo '{"read":true}';

} else {
    http_response_code(400);
    echo '{"error":"Missing id or ids parameter"}';
}
?>