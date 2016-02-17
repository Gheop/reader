<?php
include('/www/conf.php');
$mysqli->query("INSERT IGNORE INTO reader_user_item (id_user, id_item, date) VALUES ($_SESSION[user_id], $_POST[id], now());");# or die($mysqli->error);
?>