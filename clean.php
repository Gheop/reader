<?php
include('/www/conf.php');

 echo "<b>Efface des �l�ments plus vieux que trois mois :<br />"; 
 $stmt = $mysqli->prepare("delete from reader_item where pubdate < (select now() - interval  3 month);") or die($mysqli->error); 
 $stmt->execute(); 
 echo "<br /><code>".$stmt->affected_rows." �l�ments effac�s.</code><br /><br />"; 

//on efface les "marqu� comme lu" li�s pour tous les utilisateurs.
echo "<b>Effacement des �l�ments marqu�s comme lus devenus inexistants :</b>";
$stmt = $mysqli->prepare("delete FROM reader_user_item where id_item not in (select id from reader_item);") or die($mysqli->error); 
$stmt->execute();
echo "<br /><code>".$stmt->affected_rows." �l�ments effac�s.</code><br /><br />";

//effacement des doublons dans la table reader_user_item
echo "<b>Effacement des �l�ments marqu�s comme lu en doublon :</b><br />";
$r = $mysqli->query("select id FROM   gheop.reader_user_item T WHERE  T.id < ANY (SELECT id FROM   gheop.reader_user_item T2 WHERE  T.ID <> T2.ID AND  T.id_user = T2.id_user AND  T.id_item    = T2.id_item);") or die($mysqli->error);
$cpt = 0;
while($e = $r->fetch_array()) {
  $mysqli->query("delete FROM reader_user_item  WHERE  id=$e[0];") or die($mysqli->error);
  $cpt++;
}
echo "<code>$cpt �l�ments effac�s.</code>";
?>