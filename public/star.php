<?php
/**
 * Toggle star status for an article
 * POST: id (article ID)
 * Returns: {"starred": true/false}
 */
include(__DIR__ . '/../config/conf.php');
include(__DIR__ . '/../config/auth.php');

header('Content-Type: application/json');

// Security: Validate authentication and input
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(401);
    echo '{"error":"Unauthorized"}';
    exit;
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    echo '{"error":"Missing or invalid id"}';
    exit;
}

$userId = (int)$_SESSION['user_id'];
$itemId = (int)$_POST['id'];

// Check if already starred
$stmt = $mysqli->prepare("SELECT 1 FROM reader_starred_items WHERE id_user = ? AND id_item = ?");
$stmt->bind_param("ii", $userId, $itemId);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($exists) {
    // Unstar
    $stmt = $mysqli->prepare("DELETE FROM reader_starred_items WHERE id_user = ? AND id_item = ?");
    $stmt->bind_param("ii", $userId, $itemId);
    $stmt->execute();
    $stmt->close();
    echo '{"starred":false}';
} else {
    // Star
    $stmt = $mysqli->prepare("INSERT INTO reader_starred_items (id_user, id_item) VALUES (?, ?)");
    $stmt->bind_param("ii", $userId, $itemId);
    $stmt->execute();
    $stmt->close();
    echo '{"starred":true}';
}
?>
