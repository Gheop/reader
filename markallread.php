<?php
/**
 * Mark All Feed Articles as Read
 * Security: Uses prepared statements and efficient single-query approach
 */
include(__DIR__ . '/config/conf.php');

// Security: Validate authentication and input
if(!isset($_POST['f']) || !is_numeric($_POST['f']) || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(400);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$feedId = (int)$_POST['f'];

// Use single INSERT...SELECT query instead of multi_query
$stmt = $mysqli->prepare("
    INSERT INTO reader_user_item (id_user, id_item, date)
    SELECT ?, RI.id, NOW()
    FROM reader_item RI
    WHERE RI.id_flux = ?
    AND RI.id NOT IN (
        SELECT id_item FROM reader_user_item WHERE id_user = ?
    )
");
$stmt->bind_param("iii", $userId, $feedId, $userId);
$stmt->execute();
$stmt->close();
?>