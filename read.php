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

    $query = "INSERT IGNORE INTO reader_user_item (id_user, id_item, date) VALUES $valuesString";
    $mysqli->query($query);

    header('Content-Type: application/json');
    echo '{"read":true,"count":' . count($ids) . '}';

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