<?php
include(__DIR__ . '/../config/conf.php');

// Debug endpoint - restricted to admin only
if (!isset($_SESSION['pseudo']) || $_SESSION['pseudo'] !== 'SiB') {
    http_response_code(403);
    die('Access denied');
}
$user_id = (int)$_SESSION['user_id'];
$stmt = $mysqli->prepare("SELECT F.id, F.title, F.description, F.link, F.language FROM reader_user_flux U, reader_flux F WHERE U.id_user = ? AND U.id_flux = F.id AND F.update < CURDATE() ORDER BY F.update");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$r = $stmt->get_result();
$i = 0;
while($d = $r->fetch_row()) {
	$i++;
	echo "$i <a href=\"$d[3]\"><b>$d[1]</b></a> : <i>$d[2]</i> (<a href=\"//reader.gheop.com/up.php?id=$d[0]&debug\">up flux</a>) (<a href=\"//reader.gheop.com/unsubscribe_flux.php?link=$d[0]\">unsubscribe</a><br />";	
}
