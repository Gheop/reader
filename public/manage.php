<?php
include(__DIR__ . '/../config/conf.php');

// Debug endpoint - restricted to admin only
if (!isset($_SESSION['pseudo']) || $_SESSION['pseudo'] !== 'SiB') {
    http_response_code(403);
    die('Access denied');
}

$feeds = db()->fetchAll(
    "SELECT F.id, F.title, F.description, F.link, F.language
     FROM reader_user_flux U, reader_flux F
     WHERE U.id_user = ? AND U.id_flux = F.id
     ORDER BY F.title",
    [(int)$_SESSION['user_id']]
);

foreach ($feeds as $i => $d) {
    $num = $i + 1;
    echo "{$num} <a href=\"{$d['link']}\"><b>{$d['title']}</b></a> : <i>{$d['description']}</i> ";
    echo "(<a href=\"//reader.gheop.com/up.php?id={$d['id']}&debug\">up flux</a>) ";
    echo "(<a href=\"//reader.gheop.com/unsubscribe_flux.php?link={$d['id']}\">unsubscribe</a>)<br />";
}
