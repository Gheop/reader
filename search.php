<?php
include('conf.php');
if(isset($_GET['s'])) $_POST['s'] = $_GET['s'];
if(!isset($_POST['s'])) exit;
$search_string = $mysqli->real_escape_string($_POST['s']);
$lim = (isset($_POST['nb']) && is_numeric($_POST['nb']))?$_POST['nb'].', 50':'50';
$r = $mysqli->query("SELECT   @T:=UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(I.pubdate),CONCAT('{\"i\":[',GROUP_CONCAT(CONCAT('{\"i\":',I.id),CONCAT(',\"t\":\"',replace(lower(I.title),lower('$search_string'),'<span class=\'surligne\'>$search_string<\/span>'),'\"'),CONCAT(',\"p\":\"',CASE WHEN (@T < 60) THEN CONCAT(\"il y a \", FLOOR(@T), \" secondes\") WHEN (@T < 3600) THEN CONCAT(\"il y a \",FLOOR(@T/60), \" minutes\") WHEN (@T < 7200) THEN \"il y a 1 heure\" WHEN (@T < 86400) THEN CONCAT(\"il y a \", FLOOR(@T/3600), \" heures\") WHEN (@T < 31536000) THEN DATE_FORMAT(I.pubdate, \"%d\/%m %H:%i\") ELSE DATE_FORMAT(I.pubdate, \"%d\/%m\/%y %H:%i\") END,'\"'),CONCAT(',\"a\":\"',I.author,'\"'),CONCAT(',\"d\":\"',replace(lower(I.description),lower('$search_string'),'<span class=\'surligne\'>$search_string<\/span>') ,'\"'),CONCAT(',\"l\":\"',I.link,'\"'),CONCAT(',\"f\":\"',I.id_flux,'\"'),CONCAT(',\"n\":\"',F.title,'\"'),CONCAT(',\"r\":\"1\"}')),\"]}\") from reader_item I, reader_flux F, reader_user_flux U where U.id_user='$_SESSION[user_id]' and U.id_flux=I.id_flux and I.id_flux=F.id and I.pubdate > (now()- interval 45 day) and (MATCH (I.description) AGAINST ('$search_string') or MATCH(I.title) AGAINST ('$search_string')) order by pubdate limit $lim;") or die($mysqli->error);
$d = $r->fetch_row();
echo $d[1];
?>