<?php
require_once __DIR__ . '/src/OPMLGenerator.php';

use Gheop\Reader\OPMLGenerator;

include('/www/conf.php');
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) exit;

// Récupérer tous les flux de l'utilisateur
$r = $_SESSION['mysqli']->query('SELECT F.id, F.title, F.description, F.rss, F.link
    FROM reader_flux F, reader_user_flux UF
    WHERE UF.id_user='.$_SESSION['user_id'].'
    AND UF.id_flux=F.id
    ORDER BY F.title ASC') or die($_SESSION['mysqli']->error);

// Convertir le résultat en tableau
$feeds = [];
while($flux = $r->fetch_assoc()) {
    $feeds[] = $flux;
}

// Générer l'OPML
$xml = OPMLGenerator::generate($feeds);

// Envoyer les en-têtes pour le téléchargement
header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . OPMLGenerator::getFilename() . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Envoyer le XML
echo $xml;
?>
