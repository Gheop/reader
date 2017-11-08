<?php 
include('/www/conf.php');
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) exit;
//$mysqli->set_charset("utf8") or die("Erreur lors du chargement du jeu de caractères utf8 : %s\n", $mysqli->error);

/* pour test
SELECT CONCAT('{"f":[',GROUP_CONCAT(CONCAT('{"i":',F.id),CONCAT(',"t":"',REPLACE(F.title,'"','\\\"'),'"'),
CONCAT(',"n":',(select count(distinct(I.id)) - count(distinct(UI.id)) from reader_item I LEFT JOIN reader_user_item UI ON UI.id_item=I.id and UI.id_user=1 and UI.date > (now() - interval 15 day) 
where I.id_flux=F.id and I.pubdate > (now()- interval 15 day))),
CONCAT(',"d":"',REPLACE(F.description,'"','\\\"'),'"'),CONCAT(',"l":"',REPLACE(F.link,'"','\\\"'),'"}')),"]}") 
from reader_flux F, reader_user_flux UF where UF.id_user=1 and F.id=UF.id_flux  
order by F.title asc


replace into reader_tmp (id_user, menu) SELECT id_user, CONCAT('{"f":[',group_concat(
column_json(column_add(Column_create('i',F.id),'t',REPLACE(F.title,'"','\\\"'),
'n',(select count(distinct(I.id)) - count(distinct(UI.id)) from reader_item I LEFT JOIN reader_user_item UI ON UI.id_item=I.id and UI.id_user=1 and UI.date > (now() - interval 15 day) 
where I.id_flux=F.id and I.pubdate > (now()- interval 15 day)),
'd',REPLACE(F.description,'"','\\\"'),'l',REPLACE(F.link,'"','\\\"')))),"]}") 
from reader_flux F, reader_user_flux UF where UF.id_user=1 and F.id=UF.id_flux  
order by F.title;
*/
$r = $mysqli->query('SELECT CONCAT(\'{"f":[\',group_concat(
column_json(column_add(Column_create(\'i\',F.id),\'t\',F.title,
\'n\',(select count(distinct(I.id)) - count(distinct(UI.id)) from reader_item I LEFT JOIN reader_user_item UI ON UI.id_item=I.id and UI.id_user='.$_SESSION['user_id'].' and UI.date > (now() - interval 15 day) 
where I.id_flux=F.id and I.pubdate > (now()- interval 15 day)),
\'d\',F.description,\'l\',F.link))),"]}") 
from reader_flux F, reader_user_flux UF where F.id=UF.id_flux and UF.id_user='.$_SESSION['user_id'].' order by F.title asc') or die($mysqli->error);

//$r = $mysqli->query('select menu from reader_tmp where id_user=1');
//$r = $mysqli->query('SELECT CONCAT(\'{"f":[\',GROUP_CONCAT(CONCAT(\'{"i":\',F.id),CONCAT(\',"t":"\',REPLACE(F.title,\'"\',\'\\\"\'),\'"\'),CONCAT(\',"n":\',(select count(distinct(I.id)) - count(distinct(UI.id)) from reader_item I LEFT JOIN reader_user_item UI ON UI.id_item=I.id and UI.id_user='.$_SESSION['user_id'].' and UI.date > (now() - interval 15 day) where I.id_flux=F.id and I.pubdate > (now()- interval 15 day))),CONCAT(\',"d":"\',REPLACE(F.description,\'"\',\'\\\"\'),\'"\'),CONCAT(\',"l":"\',REPLACE(F.link,\'"\',\'\\\"\'),\'"}\')),"]}") from reader_flux F, reader_user_flux UF where UF.id_user='.$_SESSION['user_id'].' and F.id=UF.id_flux  order by F.title asc') or die($mysqli->error);
//if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) exit;
$d = $r->fetch_row();
//echo microtime(TRUE) - $start_time;
header('Content-Type: application/json');
echo $d[0];

//echo (!$d[0])?"{f:''}":$d[0];

//echo microtime(TRUE) - $start_time;
