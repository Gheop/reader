<?php
$site_name = 'TikTok';

function _get_URI() {
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http') . '://' .
           (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost') .
           (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
}

if(!isset($_GET['debug'])) {
    header('Content-type: application/rss+xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
';
    echo '    <atom:link href="'._get_URI().'" rel="self" type="application/rss+xml" />
';
}

if(isset($_GET['f'])) $_POST['f'] = $_GET['f'];
if(!isset($_POST['f']) || empty($_POST['f'])) {
    die("Pas de compte TikTok spécifié!");
}

$input = $_POST['f'];

// Extraire le username depuis différents types d'URLs TikTok
$username = $input;

// https://www.tiktok.com/@powerhasheur ou https://www.tiktok.com/@powerhasheur/
if (preg_match('#tiktok\.com/@([^/?]+)#', $input, $matches)) {
    $username = '@' . $matches[1];
}
// https://www.tiktok.com/@powerhasheur/video/123456789
elseif (preg_match('#tiktok\.com/@([^/]+)/video/#', $input, $matches)) {
    // Pour les URLs de vidéos individuelles, extraire quand même l'auteur
    $username = '@' . $matches[1];
}

// S'assurer que le username commence par @
if (substr($username, 0, 1) !== '@') {
    $username = '@' . $username;
}

$success = false;

// Méthode 1: Utiliser RSS-Bridge via URL publique
$rssBridgeUrl = 'https://gheop.com/rss-bridge/?action=display&bridge=TikTok&context=By+user&username=' . urlencode($username) . '&format=Mrss';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $rssBridgeUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_USERAGENT, 'Gheop Reader/1.0');

$rssBridgeOutput = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 && $rssBridgeOutput && strpos($rssBridgeOutput, '<item>') !== false) {
    // Extraire uniquement le contenu du channel
    if (preg_match('/<channel>(.*?)<\/channel>/s', $rssBridgeOutput, $matches)) {
        $channelContent = $matches[1];

        // Ajouter l'icône TikTok FontAwesome devant le titre
        $channelContent = preg_replace(
            '/<title>([^<]+)<\/title>/',
            '<title> $1</title>',
            $channelContent,
            1
        );

        echo $channelContent;
        $success = true;
    }
}

// Fallback : message d'erreur si tout a échoué
if (!$success) {
    echo "    <title> {$username}</title>\n";
    echo "    <description>Profil TikTok de {$username}</description>\n";
    echo "    <link>https://www.tiktok.com/{$username}/</link>\n";

    echo "    <item>\n";
    echo "      <title>⚠️ Flux TikTok temporairement indisponible</title>\n";
    echo "      <description><![CDATA[";
    echo "<p>Le service de scraping TikTok est temporairement indisponible.</p>";
    echo "<p>Profil : <a href='https://www.tiktok.com/{$username}/'>{$username}</a></p>";
    echo "]]></description>\n";
    echo "      <pubDate>".date('r')."</pubDate>\n";
    echo "      <link>https://www.tiktok.com/{$username}/</link>\n";
    echo "      <guid>tiktok-unavailable-{$username}-" . date('Y-m-d') . "</guid>\n";
    echo "    </item>\n";
}

?>
  </channel>
</rss>
