<?php
include('/www/conf.php');
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) exit;
//$mysqli->set_charset("utf8") or die("Erreur lors du chargement du jeu de caractères utf8 : %s\n", $mysqli->error);

//nécessité de récup ceux dont n=0 ???

$r = $mysqli->query('SELECT CONCAT(\'{\',group_concat(CONCAT(\'"\',F.id,\'":\'),
column_json(column_add(Column_create(\'t\',F.title),
\'n\',(select count(distinct(I.id)) - count(distinct(UI.id)) from reader_item I LEFT JOIN reader_user_item UI ON UI.id_item=I.id and UI.id_user='.$_SESSION['user_id'].' and UI.date > (now() - interval 15 day)
where I.id_flux=F.id and I.pubdate > (now()- interval 15 day)),
\'d\',F.description,\'l\',F.link))),"}")
from reader_flux F, reader_user_flux UF where F.id=UF.id_flux and UF.id_user='.$_SESSION['user_id'].' order by F.title asc limit 1;') or die($mysqli->error);

$d = $r->fetch_row();
//echo microtime(TRUE) - $start_time;
header('Content-Type: application/json');
echo $d[0];

//echo (!$d[0])?"{f:''}":$d[0];

//echo microtime(TRUE) - $start_time;
