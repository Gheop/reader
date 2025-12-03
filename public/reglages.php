<?php
include(__DIR__ . '/../config/conf.php');
echo '<table>';
$r = $mysqli->query("select RF.* from reader_flux RF, reader_user_flux RUF where RUF.id_user='$_SESSION[user_id]' and RUF.id_flux=RF.id order by RF.update");
while($d = $r->fetch_row()) {
echo '<tr><td>',$d[1],'</td><td>',$d[2],'</td><td><a href="',$d[3],'">',$d[3],'</a></td><td>',$d[6],'</td><td>',$d[7],'</td></tr>';	
}
echo '</table>';