<?php
/**
 * Mark Article as Unread
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
$stmt = $mysqli->prepare("DELETE FROM reader_user_item WHERE id_user = ? AND id_item = ?");
$stmt->bind_param("ii", $userId, $itemId);
$stmt->execute();
$stmt->close();
?>