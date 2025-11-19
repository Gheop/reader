<?php
$site_name = 'Reddit';

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
    die("Pas de subreddit ou d'utilisateur Reddit spécifié!");
}

$input = $_POST['f'];

// Extraire le subreddit/user depuis différents formats d'URLs Reddit
$redditUrl = $input;

// Si c'est juste un nom de subreddit (ex: "programming")
if (!preg_match('#^https?://#', $input)) {
    // Si ça commence par r/, utiliser tel quel
    if (preg_match('#^r/([^/]+)#', $input, $matches)) {
        $redditUrl = 'https://www.reddit.com/' . $input;
    }
    // Si ça commence par u/, utiliser tel quel
    elseif (preg_match('#^u/([^/]+)#', $input, $matches)) {
        $redditUrl = 'https://www.reddit.com/' . $input;
    }
    // Sinon, on suppose que c'est un nom de subreddit
    else {
        $redditUrl = 'https://www.reddit.com/r/' . $input;
    }
}

// Ajouter .rss à la fin si pas déjà présent
if (!preg_match('#\.rss$#', $redditUrl)) {
    // Enlever le slash final si présent
    $redditUrl = rtrim($redditUrl, '/');
    $redditUrl .= '.rss';
}

$success = false;

// Récupérer le flux RSS natif de Reddit
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $redditUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_USERAGENT, 'Gheop Reader/1.0');

$rssOutput = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 && $rssOutput) {
    // Reddit retourne du Atom, on va extraire et convertir en RSS
    if (strpos($rssOutput, '<feed') !== false) {
        // Parser le feed Atom
        $xml = @simplexml_load_string($rssOutput);

        if ($xml) {
            // Extraire le titre et ajouter l'icône Reddit
            $title = (string)$xml->title;
            $subtitle = isset($xml->subtitle) ? (string)$xml->subtitle : '';
            $link = '';

            // Trouver le lien alternatif
            foreach ($xml->link as $l) {
                if ((string)$l['rel'] === 'alternate' && (string)$l['type'] === 'text/html') {
                    $link = (string)$l['href'];
                    break;
                }
            }

            echo "    <title> {$title}</title>\n";
            echo "    <description>".htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8')."</description>\n";
            echo "    <link>{$link}</link>\n";

            // Traiter les entrées
            foreach ($xml->entry as $entry) {
                $itemTitle = (string)$entry->title;
                $itemLink = '';

                foreach ($entry->link as $l) {
                    $itemLink = (string)$l['href'];
                    break;
                }

                $itemContent = isset($entry->content) ? (string)$entry->content : '';
                $itemDate = isset($entry->updated) ? date('r', strtotime((string)$entry->updated)) : date('r');
                $itemGuid = (string)$entry->id;

                echo "    <item>\n";
                echo "      <title>".htmlspecialchars($itemTitle, ENT_QUOTES, 'UTF-8')."</title>\n";
                echo "      <description><![CDATA[".$itemContent."]]></description>\n";
                echo "      <pubDate>{$itemDate}</pubDate>\n";
                echo "      <link>{$itemLink}</link>\n";
                echo "      <guid>{$itemGuid}</guid>\n";
                echo "    </item>\n";
            }

            $success = true;
        }
    }
}

// Fallback : message d'erreur si tout a échoué
if (!$success) {
    echo "    <title> Reddit</title>\n";
    echo "    <description>Flux Reddit</description>\n";
    echo "    <link>https://www.reddit.com/</link>\n";

    echo "    <item>\n";
    echo "      <title>⚠️ Flux Reddit temporairement indisponible</title>\n";
    echo "      <description><![CDATA[";
    echo "<p>Le flux Reddit est temporairement indisponible.</p>";
    echo "<p>URL: <a href='{$redditUrl}'>{$redditUrl}</a></p>";
    echo "]]></description>\n";
    echo "      <pubDate>".date('r')."</pubDate>\n";
    echo "      <link>https://www.reddit.com/</link>\n";
    echo "      <guid>reddit-unavailable-" . md5($redditUrl) . "-" . date('Y-m-d') . "</guid>\n";
    echo "    </item>\n";
}

?>
  </channel>
</rss>
