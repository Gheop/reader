<?php
include('/www/conf.php');
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) exit;

// Récupérer tous les flux de l'utilisateur
$r = $_SESSION['mysqli']->query('SELECT F.id, F.title, F.description, F.rss, F.link
    FROM reader_flux F, reader_user_flux UF
    WHERE UF.id_user='.$_SESSION['user_id'].'
    AND UF.id_flux=F.id
    ORDER BY F.title ASC') or die($_SESSION['mysqli']->error);

// Créer le document OPML
$opml = new DOMDocument('1.0', 'UTF-8');
$opml->formatOutput = true;

// Élément racine
$root = $opml->createElement('opml');
$root->setAttribute('version', '2.0');
$opml->appendChild($root);

// Head
$head = $opml->createElement('head');
$root->appendChild($head);

$title = $opml->createElement('title', 'Gheop Reader Feeds Export');
$head->appendChild($title);

$dateCreated = $opml->createElement('dateCreated', date('r'));
$head->appendChild($dateCreated);

// Body
$body = $opml->createElement('body');
$root->appendChild($body);

// Ajouter chaque flux
while($flux = $r->fetch_assoc()) {
    // Valider et nettoyer les URLs
    $xmlUrl = trim($flux['rss']);
    $htmlUrl = trim($flux['link']);

    // Vérifier que les URLs sont valides (commencent par http:// ou https://)
    if(!preg_match('/^https?:\/\/.+/', $xmlUrl)) {
        continue; // Ignorer les flux avec URLs invalides
    }

    // Si htmlUrl est invalide ou vide, utiliser xmlUrl
    if(empty($htmlUrl) || !preg_match('/^https?:\/\/.+/', $htmlUrl)) {
        $htmlUrl = $xmlUrl;
    }

    $outline = $opml->createElement('outline');
    $outline->setAttribute('type', 'rss');
    $outline->setAttribute('text', htmlspecialchars($flux['title'], ENT_XML1, 'UTF-8'));
    $outline->setAttribute('title', htmlspecialchars($flux['title'], ENT_XML1, 'UTF-8'));
    $outline->setAttribute('xmlUrl', htmlspecialchars($xmlUrl, ENT_XML1, 'UTF-8'));
    $outline->setAttribute('htmlUrl', htmlspecialchars($htmlUrl, ENT_XML1, 'UTF-8'));
    if(!empty($flux['description'])) {
        $outline->setAttribute('description', htmlspecialchars($flux['description'], ENT_XML1, 'UTF-8'));
    }
    $body->appendChild($outline);
}

// Envoyer les en-têtes pour le téléchargement
header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="gheop-reader-feeds-' . date('Y-m-d') . '.opml"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Générer et envoyer le XML
echo $opml->saveXML();
?>
