<?php
include('/www/conf.php');
$r = $mysqli->query('select RI.id from reader_item RI where RI.id_flux='.$_POST['f'].' and RI.id not in (select id_item from reader_user_item as UI where UI.id_user='.$_SESSION['user_id'].')') or die($mysqli->error);
//$t = array();
$query = '';
while($e = $r->fetch_array()) {
 // $t[] = $e[0];
  $query .= "insert into reader_user_item (id_user, id_item, date) values($_SESSION[user_id], $e[0], now());";
}
$mysqli->multi_query($query);
// foreach($t as $i) {
//   $mysqli->query("insert into reader_user_item (id_user, id_item, date) values($_SESSION[user_id], $i, now());") or die($mysqli->error);
// }
?>