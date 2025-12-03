<?php
/**
 * Mark Article(s) as Read
 * Supports batch operations via 'ids' parameter (comma-separated)
 * Security: Uses prepared statements to prevent SQL injection
 */
include(__DIR__ . '/config/conf.php');
include(__DIR__ . '/config/auth.php');

// Query performance monitoring helper
function logSlowQuery($queryName, $duration, $threshold = 100) {
    if ($duration > $threshold) {
        error_log(sprintf("SLOW QUERY [%s]: %.2fms (threshold: %dms)", $queryName, $duration, $threshold));
    }
}

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
        $query_start = microtime(true);
        $result = $mysqli->query($query);
        logSlowQuery('read.php - batch insert', (microtime(true) - $query_start) * 1000);

        if (!$result) {
            throw new Exception("Insert failed: " . $mysqli->error);
        }

        // Remove from unread cache
        $idsString = implode(',', $ids);
        $deleteQuery = "DELETE FROM reader_unread_cache WHERE id_user = $userId AND id_item IN ($idsString)";
        $query_start = microtime(true);
        $deleteResult = $mysqli->query($deleteQuery);
        logSlowQuery('read.php - cache delete', (microtime(true) - $query_start) * 1000);

        if (!$deleteResult) {
            throw new Exception("Cache delete failed: " . $mysqli->error);
        }

        // Update feed counter in reader_flux_user_stats
        // Use incremental decrement instead of full COUNT for performance
        $updateQuery = "
            UPDATE reader_flux_user_stats S
            INNER JOIN (
                SELECT I.id_flux, COUNT(*) as cnt
                FROM reader_item I
                WHERE I.id IN ($idsString)
                GROUP BY I.id_flux
            ) AS Counts ON Counts.id_flux = S.id_flux
            SET S.unread_count = GREATEST(0, S.unread_count - Counts.cnt)
            WHERE S.id_user = $userId
        ";
        $query_start = microtime(true);
        $updateResult = $mysqli->query($updateQuery);
        logSlowQuery('read.php - counter update', (microtime(true) - $query_start) * 1000);

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
    // Single ID mode - now handles cache/counters manually (no triggers needed)
    $itemId = (int)$_POST['id'];

    // Start transaction
    $mysqli->begin_transaction();

    try {
        // Mark as read
        $stmt = $mysqli->prepare("INSERT IGNORE INTO reader_user_item (id_user, id_item, date) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $userId, $itemId);
        $query_start = microtime(true);
        $stmt->execute();
        logSlowQuery('read.php - single insert', (microtime(true) - $query_start) * 1000);
        $affected = $stmt->affected_rows;
        $stmt->close();

        // Only update cache/counters if the article was actually marked as read (not already read)
        if ($affected > 0) {
            // Remove from unread cache
            $stmt = $mysqli->prepare("DELETE FROM reader_unread_cache WHERE id_user = ? AND id_item = ?");
            $stmt->bind_param("ii", $userId, $itemId);
            $query_start = microtime(true);
            $stmt->execute();
            logSlowQuery('read.php - single cache delete', (microtime(true) - $query_start) * 1000);
            $stmt->close();

            // Update feed counter in reader_flux_user_stats
            // Use incremental decrement instead of full COUNT for performance
            $stmt = $mysqli->prepare("
                UPDATE reader_flux_user_stats S
                INNER JOIN reader_item I ON I.id = ? AND I.id_flux = S.id_flux
                SET S.unread_count = GREATEST(0, S.unread_count - 1)
                WHERE S.id_user = ?
            ");
            $stmt->bind_param("ii", $itemId, $userId);
            $query_start = microtime(true);
            $stmt->execute();
            logSlowQuery('read.php - single counter update', (microtime(true) - $query_start) * 1000);
            $stmt->close();
        }

        $mysqli->commit();

        header('Content-Type: application/json');
        echo '{"read":true}';

    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Single read failed: " . $e->getMessage());
        http_response_code(500);
        echo '{"error":"Database error"}';
    }

} else {
    http_response_code(400);
    echo '{"error":"Missing id or ids parameter"}';
}
?>