<?php
$site_name = 'Twitter/X';

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
    die("Pas de compte Twitter/X spécifié!");
}

$input = $_POST['f'];

// Extraire le username depuis différents formats d'URLs Twitter/X
$username = $input;

// Nettoyer les différents formats d'URL
if (preg_match('#(twitter\.com|x\.com)/([^/?]+)#', $input, $matches)) {
    $username = $matches[2];
}

// Enlever @ si présent
$username = ltrim($username, '@');

// Construire l'URL Google News RSS pour chercher les tweets de ce compte
// Format: site:x.com/username when:7d (derniers 7 jours)
$googleNewsUrl = 'https://news.google.com/rss/search?q=' . urlencode('site:x.com/' . $username . ' when:7d') . '&hl=fr&gl=FR&ceid=FR:fr';

$success = false;

// Récupérer le flux Google News
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $googleNewsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_USERAGENT, 'Gheop Reader/1.0');

$rssOutput = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 && $rssOutput && strpos($rssOutput, '<item>') !== false) {
    // Parser le flux RSS de Google News
    $xml = @simplexml_load_string($rssOutput);

    if ($xml && isset($xml->channel)) {
        // Personnaliser le titre avec l'icône Twitter
        echo "    <title> @{$username}</title>\n";
        echo "    <description>Tweets de @{$username} via Google News</description>\n";
        echo "    <link>https://x.com/{$username}</link>\n";

        // Traiter les items
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $itemTitle = (string)$item->title;
                $itemLink = (string)$item->link;
                $itemDescription = isset($item->description) ? (string)$item->description : '';
                $itemDate = isset($item->pubDate) ? (string)$item->pubDate : date('r');
                $itemGuid = (string)$item->guid;

                echo "    <item>\n";
                echo "      <title>".htmlspecialchars($itemTitle, ENT_QUOTES, 'UTF-8')."</title>\n";
                echo "      <description><![CDATA[".$itemDescription."]]></description>\n";
                echo "      <pubDate>{$itemDate}</pubDate>\n";
                echo "      <link>{$itemLink}</link>\n";
                echo "      <guid>{$itemGuid}</guid>\n";
                echo "    </item>\n";
            }

            $success = true;
        }
    }
}

// Fallback : message informatif (uniquement si aucun tweet trouvé)
if (!$success) {
    echo "    <title> @{$username}</title>\n";
    echo "    <description>Tweets de @{$username} via Google News</description>\n";
    echo "    <link>https://x.com/{$username}</link>\n";

    // Ne pas créer d'article si le flux est vide - le reader affichera "Flux vide"
    // Cela évite de créer un article "info" à chaque update
}

?>
  </channel>
</rss>
