<?php
/**
 * Mark Article as Read
 * Security: Uses prepared statements to prevent SQL injection
 */
include('/www/conf.php');

// Security: Validate authentication and input
if(!isset($_POST['id']) || !is_numeric($_POST['id']) || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(400);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$itemId = (int)$_POST['id'];

// Use prepared statement
$stmt = $mysqli->prepare("INSERT IGNORE INTO reader_user_item (id_user, id_item, date) VALUES (?, ?, NOW())");
$stmt->bind_param("ii", $userId, $itemId);
$stmt->execute();
$stmt->close();

header('Content-Type: application/json');
echo '{"read":true}';
?>