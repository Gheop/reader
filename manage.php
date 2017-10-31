<?php
include('/www/conf.php');
if(!isset($_SESSION['user_id'])) {
  echo "Vous n'êtes pas authentifié sur Gheop!";
  exit;
}
$r = $mysqli->query("select F.id,F.title, F.description, F.link, F.language from reader_user_flux U, reader_flux F where U.id_user=$_SESSION[user_id] and U.id_flux=F.id order by F.title");
$i = 0;
while($d = $r->fetch_row()) {
	$i++;
	echo "$i <a href=\"$d[3]\"><b>$d[1]</b></a> : <i>$d[2]</i> (<a href=\"//reader.gheop.com/up.php?id=$d[0]&debug\">up flux</a>)  (<a href=\"//reader.gheop.com/unsubscribe_flux.php?link=$d[0]\">unsubscribe</a>)<br />";	
}
