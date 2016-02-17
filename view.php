<?php
include('/www/conf.php');
/*if (!$mysqli->set_charset("utf8")) {
    printf("Erreur lors du chargement du jeu de caractères utf8 : %s\n", $mysqli->error);
}*/
//$mysqli->query("SET [GLOBAL | SESSION] group_concat_max_len = 2000000;"); // ou à mettre en dur dans my.cnf
//if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) exit;
$lim = (isset($_POST['nb']) && is_numeric($_POST['nb']))?$_POST['nb'].', 50':'50';
$id = (isset($_POST['id']) && is_numeric($_POST['id']))?' and F.id='.$_POST['id']:'';
$r = $mysqli->query("SELECT   @T:=UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(I.pubdate),CONCAT('{\"i\":[',GROUP_CONCAT(CONCAT('{\"i\":',I.id),CONCAT(',\"t\":\"',I.title,'\"'),CONCAT(',\"p\":\"',CASE WHEN (@T < 60) THEN CONCAT(\"il y a \", FLOOR(@T), \" secondes\") WHEN (@T < 3600) THEN CONCAT(\"il y a \",FLOOR(@T/60), \" minutes\") WHEN (@T < 7200) THEN \"il y a 1 heure\" WHEN (@T < 86400) THEN CONCAT(\"il y a \", FLOOR(@T/3600), \" heures\") WHEN (@T < 31536000) THEN DATE_FORMAT(I.pubdate, \"%d\/%m %H:%i\") ELSE DATE_FORMAT(I.pubdate, \"%d\/%m\/%y %H:%i\") END,'\"'),CONCAT(',\"a\":\"',I.author,'\"'),CONCAT(',\"d\":\"',I.description,'\"'),CONCAT(',\"l\":\"',I.link,'\"'),CONCAT(',\"f\":\"',I.id_flux,'\"'),CONCAT(',\"n\":\"',F.title,'\"'),CONCAT(',\"r\":\"1\"}')),\"]}\") from reader_item I, reader_flux F, reader_user_flux U where U.id_user='$_SESSION[user_id]' and U.id_flux=I.id_flux and I.id_flux=F.id$id and I.id not in (select id_item from reader_user_item as UI where UI.id_user='$_SESSION[user_id]' and UI.date > (now() - interval 15 day)) and I.pubdate > (now()- interval 15 day) order by pubdate desc limit $lim;");
$d = $r->fetch_row();
echo $d[1];
?>
