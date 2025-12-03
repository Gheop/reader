<?php
include(__DIR__ . '/../config/conf.php');
echo '<style>
.CSSTableGenerator {
	margin:0px;padding:0px;
	width:100%;
	box-shadow: 10px 10px 5px #888888;
	border:1px solid #b2b2b2;
	
	-moz-border-radius-bottomleft:5px;
	-webkit-border-bottom-left-radius:5px;
	border-bottom-left-radius:5px;
	
	-moz-border-radius-bottomright:5px;
	-webkit-border-bottom-right-radius:5px;
	border-bottom-right-radius:5px;
	
	-moz-border-radius-topright:5px;
	-webkit-border-top-right-radius:5px;
	border-top-right-radius:5px;
	
	-moz-border-radius-topleft:5px;
	-webkit-border-top-left-radius:5px;
	border-top-left-radius:5px;
}.CSSTableGenerator table{
    border-collapse: collapse;
        border-spacing: 0;
	width:100%;
	height:100%;
	margin:0px;padding:0px;
}.CSSTableGenerator tr:last-child td:last-child {
	-moz-border-radius-bottomright:5px;
	-webkit-border-bottom-right-radius:5px;
	border-bottom-right-radius:5px;
}
.CSSTableGenerator table tr:first-child td:first-child {
	-moz-border-radius-topleft:5px;
	-webkit-border-top-left-radius:5px;
	border-top-left-radius:5px;
}
.CSSTableGenerator table tr:first-child td:last-child {
	-moz-border-radius-topright:5px;
	-webkit-border-top-right-radius:5px;
	border-top-right-radius:5px;
}.CSSTableGenerator tr:last-child td:first-child{
	-moz-border-radius-bottomleft:5px;
	-webkit-border-bottom-left-radius:5px;
	border-bottom-left-radius:5px;
}.CSSTableGenerator tr:hover td{
	
}
.CSSTableGenerator tr:nth-child(odd){ background-color:#ffffff; }
.CSSTableGenerator tr:nth-child(even)    { background-color:#e5e5e5; }.CSSTableGenerator td{
	vertical-align:middle;
	
	
	border:1px solid #b2b2b2;
	border-width:0px 1px 1px 0px;
	text-align:left;
	padding:7px;
	font-size:10px;
	font-family:Arial;
	font-weight:normal;
	color:#191919;
}.CSSTableGenerator tr:last-child td{
	border-width:0px 1px 0px 0px;
}.CSSTableGenerator tr td:last-child{
	border-width:0px 0px 1px 0px;
}.CSSTableGenerator tr:last-child td:last-child{
	border-width:0px 0px 0px 0px;
}
.CSSTableGenerator tr:first-child td{
		background:-o-linear-gradient(bottom, #ff7f00 5%, #ffaa56 100%);	background:-webkit-gradient( linear, left top, left bottom, color-stop(0.05, #ff7f00), color-stop(1, #ffaa56) );
	background:-moz-linear-gradient( center top, #ff7f00 5%, #ffaa56 100% );
	filter:progid:DXImageTransform.Microsoft.gradient(startColorstr="#ff7f00", endColorstr="#ffaa56");	background: -o-linear-gradient(top,#ff7f00,ffaa56);

	background-color:#ff7f00;
	border:0px solid #b2b2b2;
	text-align:center;
	border-width:0px 0px 1px 1px;
	font-size:14px;
	font-family:Verdana;
	font-weight:bold;
	color:#ffd4aa;
}
.CSSTableGenerator tr:first-child:hover td{
	background:-o-linear-gradient(bottom, #ff7f00 5%, #ffaa56 100%);	background:-webkit-gradient( linear, left top, left bottom, color-stop(0.05, #ff7f00), color-stop(1, #ffaa56) );
	background:-moz-linear-gradient( center top, #ff7f00 5%, #ffaa56 100% );
	filter:progid:DXImageTransform.Microsoft.gradient(startColorstr="#ff7f00", endColorstr="#ffaa56");	background: -o-linear-gradient(top,#ff7f00,ffaa56);

	background-color:#ff7f00;
}
.CSSTableGenerator tr:first-child td:first-child{
	border-width:0px 0px 1px 0px;
}
.CSSTableGenerator tr:first-child td:last-child{
	border-width:0px 0px 1px 1px;
}
</style>';
if (!$mysqli->set_charset("utf8")) {
    printf("Erreur lors du chargement du jeu de caractères utf8 : %s\n", $mysqli->error);
}
//$mysqli->query("SET [GLOBAL | SESSION] group_concat_max_len = 2000000;"); // ou à mettre en dur dans my.cnf
//if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) exit;
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) die;
$item_id = (int)$_GET['id'];

$stmt = $mysqli->prepare("SELECT id, id_flux, pubdate, guid, title, author, link, description FROM reader_item WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$r = $stmt->get_result();
$d = $r->fetch_row();
for($i=0; $i<8;$i++) {
	$a = array( '\"', '\/');
  	$b = array('"','/');
  	$d[$i]  = str_replace($a, $b, $d[$i] );
}
echo "<div class=\"CSSTableGenerator\" >\n";
echo "<table>\n";
echo "\t<tr>\n\t\t<td>id</td>\n\t\t<td>$d[0]</td>\n\t</tr>\n";
echo "\t<tr>\n\t\t<td>id_flux</td>\n\t\t<td>",$d[1],' (<a href="//reader.gheop.com/up.php?id=',$d[1],"&debug\">up flux</a>)</td>\n\t</tr>\n";
echo "\t<tr>\n\t\t<td>pubdate</td>\n\t\t<td>$d[2]</td>\n\t</tr>\n";
echo "\t<tr>\n\t\t<td>guid</td>\n\t\t<td>$d[3]</td>\n\t</tr>\n";
echo "\t<tr>\n\t\t<td>title</td>\n\t\t<td>$d[4]</td>\n\t</tr>\n";
echo "\t<tr>\n\t\t<td>author</td>\n\t\t<td>$d[5]</td>\n\t</tr>\n";
echo "\t<tr>\n\t\t<td>link</td>\n\t\t<td>$d[6]</td>\n\t</tr>\n";
echo "\t<tr>\n\t\t<td>description</td>\n\t\t<td>$d[7]</td>\n\t</tr>\n";
echo "</table></div>";
?>
