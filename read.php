<?php
include('/www/conf.php');
if(!isset($_POST['id']) || !is_numeric($_POST['id']) || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) exit;
$mysqli->query("INSERT IGNORE INTO reader_user_item (id_user, id_item, date) VALUES ($_SESSION[user_id], $_POST[id], now());");
header('Content-Type: application/json');
echo '{ "read":true } ';
?>