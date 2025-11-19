<?php
require('simple_html_dom.php');
include('../clean_text.php');

$site_name = 'Instagram';

function _get_URI() {
    return ($_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
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
    die("Pas de compte Instagram spécifié!");
}

$input = $_POST['f'];

// Extraire le username depuis différents types d'URLs Instagram
$username = $input;

// https://www.instagram.com/ledger/ ou https://www.instagram.com/ledger
if (preg_match('#instagram\.com/([^/?]+)#', $input, $matches)) {
    $username = $matches[1];
}
// https://www.instagram.com/stories/ledger/ ou https://www.instagram.com/stories/ledger
elseif (preg_match('#instagram\.com/stories/([^/?]+)#', $input, $matches)) {
    $username = $matches[1];
}
// https://www.instagram.com/p/ABC123/ (post URL)
elseif (preg_match('#instagram\.com/p/[^/]+#', $input)) {
    // Pour les URLs de posts individuels, on ne peut pas suivre le profil
    // Il faudrait scraper le post pour trouver l'auteur
    die("Erreur : Veuillez utiliser l'URL du profil, pas d'un post individuel.");
}
// https://www.instagram.com/reel/ABC123/
elseif (preg_match('#instagram\.com/reel/[^/]+#', $input)) {
    die("Erreur : Veuillez utiliser l'URL du profil, pas d'un reel individuel.");
}

// Nettoyer le username (enlever @ si présent)
$username = ltrim($username, '@');

$success = false;

// Méthode 1: Utiliser RSS-Bridge local (InstagramBridge)
$rssBridgePath = '/www/rss-bridge';
if (file_exists($rssBridgePath . '/index.php')) {
    // Simuler une requête à RSS-Bridge
    $_GET['action'] = 'display';
    $_GET['bridge'] = 'Instagram';
    $_GET['context'] = 'Username';
    $_GET['u'] = $username;
    $_GET['format'] = 'Mrss';

    // Capturer la sortie de RSS-Bridge
    ob_start();
    try {
        include($rssBridgePath . '/index.php');
        $rssBridgeOutput = ob_get_clean();

        // Si RSS-Bridge a généré du XML valide, l'utiliser
        if (strpos($rssBridgeOutput, '<item>') !== false && strpos($rssBridgeOutput, '</item>') !== false) {
            // Extraire uniquement le contenu du channel
            if (preg_match('/<channel>(.*?)<\/channel>/s', $rssBridgeOutput, $matches)) {
                $channelContent = $matches[1];

                // Ajouter l'icône Instagram FontAwesome devant le titre
                $channelContent = preg_replace(
                    '/<title>([^<]+)<\/title>/',
                    '<title> $1</title>',
                    $channelContent,
                    1
                );

                echo $channelContent;
                $success = true;
            }
        }
    } catch (Exception $e) {
        ob_end_clean();
    }
}

// Méthode 2: Essayer via Picuki (proxy Instagram populaire)
if (!$success) {
    $picukiUrl = "https://www.picuki.com/profile/" . urlencode($username);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $picukiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Cache-Control: no-cache'
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200 && $response && strlen($response) > 1000) {
        $html = str_get_html($response);

        if ($html) {
            $biography = '';
            $bioElement = $html->find('.profile-bio', 0);
            if ($bioElement) {
                $biography = trim($bioElement->plaintext);
            }

            echo "    <title> {$username}</title>\n";
            echo "    <description>".htmlspecialchars($biography, ENT_QUOTES, 'UTF-8')."</description>\n";
            echo "    <link>https://www.instagram.com/{$username}/</link>\n";

            // Récupérer les posts
            $posts = $html->find('.box-photos .box-photo');

            if (count($posts) > 0) {
                foreach (array_slice($posts, 0, 12) as $post) {
                    $linkElement = $post->find('a', 0);
                    if (!$linkElement) continue;

                    $postUrl = $linkElement->href;
                    // Convertir l'URL Picuki vers Instagram
                    $postUrl = str_replace('picuki.com/media/', 'instagram.com/p/', $postUrl);
                    if (!preg_match('/^https?:\/\//', $postUrl)) {
                        $postUrl = 'https://www.instagram.com' . $postUrl;
                    }

                    $imgElement = $post->find('img.post-image', 0);
                    $imageUrl = $imgElement ? $imgElement->src : '';

                    $captionElement = $post->find('.photo-description', 0);
                    $caption = $captionElement ? trim($captionElement->plaintext) : '';

                    $timeElement = $post->find('time', 0);
                    $timestamp = $timeElement && $timeElement->datetime ? strtotime($timeElement->datetime) : time();
                    $date = date('r', $timestamp);

                    // Construire le contenu
                    $content = '';
                    if ($imageUrl) {
                        $content .= '<img src="'.$imageUrl.'" /><br /><br />';
                    }
                    if ($caption) {
                        $content .= nl2br(htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'));
                    }

                    // Titre court
                    $title = $caption ? cutString($caption, 0, 100) : "Photo Instagram";

                    echo "    <item>\n";
                    echo "      <title>".htmlspecialchars($title, ENT_QUOTES, 'UTF-8')."</title>\n";
                    echo "      <description><![CDATA[".$content."]]></description>\n";
                    echo "      <pubDate>{$date}</pubDate>\n";
                    echo "      <link>{$postUrl}</link>\n";
                    echo "      <guid>{$postUrl}</guid>\n";
                    echo "    </item>\n";
                }

                $success = true;
                $html->clear();
            }
        }
    }
}

// Fallback : message d'erreur si tout a échoué
if (!$success) {
    echo "    <title> {$username}</title>\n";
    echo "    <description>Profil Instagram de {$username}</description>\n";
    echo "    <link>https://www.instagram.com/{$username}/</link>\n";

    echo "    <item>\n";
    echo "      <title>⚠️ Flux Instagram temporairement indisponible</title>\n";
    echo "      <description><![CDATA[";
    echo "<p>Les services de scraping Instagram sont temporairement indisponibles.</p>";
    echo "<p>Profil : <a href='https://www.instagram.com/{$username}/'>@{$username}</a></p>";
    echo "]]></description>\n";
    echo "      <pubDate>".date('r')."</pubDate>\n";
    echo "      <link>https://www.instagram.com/{$username}/</link>\n";
    echo "      <guid>instagram-unavailable-{$username}-" . date('Y-m-d') . "</guid>\n";
    echo "    </item>\n";
}

?>
  </channel>
</rss>
