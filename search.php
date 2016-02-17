<?php
include('/www/conf.php');
//$mysqli->query("SET [GLOBAL | SESSION] group_concat_max_len = 2000000;"); // ou Ã  mettre en dur dans my.cnf
//if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) exit;
$lim = (isset($_POST['nb']) && is_numeric($_POST['nb']))?$_POST['nb'].', 50':'50';
//$_POST['s'] = 'linux';
if(!isset($_POST['s'])) exit;
//$search = $_POST['s'];
//$search = $_GET['s'];
//echo 'FUCK';

//recherche sur le couple title+desc, marche plus depuis que j'ai passé description en mediumtext, impossible de créer un index sur le couple
//$r = $mysqli->query("SELECT   @T:=UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(I.pubdate),CONCAT('{\"i\":[',GROUP_CONCAT(CONCAT('{\"i\":',I.id),CONCAT(',\"t\":\"',replace(lower(I.title),lower('$_POST[s]'),'<span class=\'surligne\'>$_POST[s]<\/span>'),'\"'),CONCAT(',\"p\":\"',CASE WHEN (@T < 60) THEN CONCAT(\"il y a \", FLOOR(@T), \" secondes\") WHEN (@T < 3600) THEN CONCAT(\"il y a \",FLOOR(@T/60), \" minutes\") WHEN (@T < 7200) THEN \"il y a 1 heure\" WHEN (@T < 86400) THEN CONCAT(\"il y a \", FLOOR(@T/3600), \" heures\") WHEN (@T < 31536000) THEN DATE_FORMAT(I.pubdate, \"%d\/%m %H:%i\") ELSE DATE_FORMAT(I.pubdate, \"%d\/%m\/%y %H:%i\") END,'\"'),CONCAT(',\"a\":\"',I.author,'\"'),CONCAT(',\"d\":\"',replace(lower(I.description),lower('$_POST[s]'),'<span class=\'surligne\'>$_POST[s]<\/span>') ,'\"'),CONCAT(',\"l\":\"',I.link,'\"'),CONCAT(',\"f\":\"',I.id_flux,'\"'),CONCAT(',\"n\":\"',F.title,'\"'),CONCAT(',\"r\":\"1\"}')),\"]}\") from reader_item I, reader_flux F, reader_user_flux U where U.id_user='$_SESSION[user_id]' and U.id_flux=I.id_flux and I.id_flux=F.id and I.pubdate > (now()- interval 45 day) and MATCH (I.title,I.description) AGAINST ('$_POST[s]') order by pubdate desc limit $lim;") or die($mysqli->error);
$r = $mysqli->query("SELECT   @T:=UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(I.pubdate),CONCAT('{\"i\":[',GROUP_CONCAT(CONCAT('{\"i\":',I.id),CONCAT(',\"t\":\"',replace(lower(I.title),lower('$_POST[s]'),'<span class=\'surligne\'>$_POST[s]<\/span>'),'\"'),CONCAT(',\"p\":\"',CASE WHEN (@T < 60) THEN CONCAT(\"il y a \", FLOOR(@T), \" secondes\") WHEN (@T < 3600) THEN CONCAT(\"il y a \",FLOOR(@T/60), \" minutes\") WHEN (@T < 7200) THEN \"il y a 1 heure\" WHEN (@T < 86400) THEN CONCAT(\"il y a \", FLOOR(@T/3600), \" heures\") WHEN (@T < 31536000) THEN DATE_FORMAT(I.pubdate, \"%d\/%m %H:%i\") ELSE DATE_FORMAT(I.pubdate, \"%d\/%m\/%y %H:%i\") END,'\"'),CONCAT(',\"a\":\"',I.author,'\"'),CONCAT(',\"d\":\"',replace(lower(I.description),lower('$_POST[s]'),'<span class=\'surligne\'>$_POST[s]<\/span>') ,'\"'),CONCAT(',\"l\":\"',I.link,'\"'),CONCAT(',\"f\":\"',I.id_flux,'\"'),CONCAT(',\"n\":\"',F.title,'\"'),CONCAT(',\"r\":\"1\"}')),\"]}\") from reader_item I, reader_flux F, reader_user_flux U where U.id_user='$_SESSION[user_id]' and U.id_flux=I.id_flux and I.id_flux=F.id and I.pubdate > (now()- interval 45 day) and (MATCH (I.description) AGAINST ('$_POST[s]') or MATCH(I.title) AGAINST ('$_POST[s]')) order by pubdate desc limit $lim;") or die($mysqli->error);




$d = $r->fetch_row();
echo $d[1];
?>