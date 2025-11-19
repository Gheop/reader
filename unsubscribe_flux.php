<?php
/**
 * Unsubscribe from RSS Feed
 * Security: Properly uses prepared statements with parameter binding
 */
include('/www/conf.php');

// Security: Validate authentication
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

// Get feed ID from POST or GET
$feedId = null;
if(isset($_POST['link'])) {
    $feedId = $_POST['link'];
} elseif(isset($_GET['link'])) {
    $feedId = $_GET['link'];
} else {
    http_response_code(400);
    echo "Error : Pas de flux trouvé !";
    exit;
}

// Validate feed ID is numeric
if(!is_numeric($feedId)) {
    http_response_code(400);
    die('id error');
}

$userId = (int)$_SESSION['user_id'];
$feedId = (int)$feedId;

// Remove user subscription
$stmt = $mysqli->prepare("DELETE FROM reader_user_flux WHERE id_flux = ? AND id_user = ?");
$stmt->bind_param("ii", $feedId, $userId);
$stmt->execute() or die("error");
$stmt->close();

// Check if any other users are subscribed to this feed
$stmt = $mysqli->prepare("SELECT id FROM reader_user_flux WHERE id_flux = ?");
$stmt->bind_param("i", $feedId);
$stmt->execute();
$stmt->store_result();

// If no other subscribers, delete the feed entirely
if($stmt->num_rows == 0) {
    $stmt->close();
    $stmt = $mysqli->prepare("DELETE FROM reader_flux WHERE id = ?");
    $stmt->bind_param("i", $feedId);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt->close();
}

$mysqli->close();
?>
