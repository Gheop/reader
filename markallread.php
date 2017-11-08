<?php
include('/www/conf.php');
if(!isset($_POST['f']) || !is_numeric($_POST['f']) || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) exit;$r = $mysqli->query('select RI.id from reader_item RI where RI.id_flux='.$_POST['f'].' and RI.id not in (select id_item from reader_user_item as UI where UI.id_user='.$_SESSION['user_id'].')') or die($mysqli->error);
$query = '';
while($e = $r->fetch_array()) {
  $query .= "insert into reader_user_item (id_user, id_item, date) values($_SESSION[user_id], $e[0], now());";
}
$mysqli->multi_query($query);
?>