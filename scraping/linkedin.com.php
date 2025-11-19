<?php
require('simple_html_dom.php');
include('../clean_text.php');

$site_name = 'LinkedIn';

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
if(!isset($_POST['f']) || empty($_POST['f'])) die("Pas de profil LinkedIn spécifié!");

$profile = $_POST['f'];

// Extraction du username depuis l'URL si besoin
if(preg_match('/linkedin\.com\/in\/([^\/]+)/', $profile, $matches)) {
    $profile = $matches[1];
}

// Remove trailing slash
$profile = rtrim($profile, '/');

$profile_url = 'https://www.linkedin.com/in/' . $profile . '/';

echo "    <title>LinkedIn - ".$profile."</title>
    <description>Derniers posts de ".$profile." sur LinkedIn</description>
    <link>".$profile_url."</link>
";

// LinkedIn rend le scraping très difficile. On va tenter plusieurs approches:
// 1. Essayer avec l'URL des activités publiques
// 2. Utiliser un User-Agent réaliste
// 3. Parser le HTML disponible

$activities_url = 'https://www.linkedin.com/in/' . $profile . '/recent-activity/all/';

$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_URL => $activities_url,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => 'UTF-8',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_HTTPHEADER => array(
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
        'Cache-Control: no-cache',
        'Pragma: no-cache'
    )
));

$html_content = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if(isset($_GET['debug'])) {
    echo "<h2>Debug Mode - HTTP Code: $http_code</h2>";
    echo "<pre>" . htmlspecialchars(substr($html_content, 0, 5000)) . "</pre>";
    die();
}

$items_found = false;

if($html_content && $http_code == 200) {
    $html = str_get_html($html_content);

    if($html) {
        // Tentative 1: Chercher les posts dans les divs avec class contenant "feed"
        $posts = $html->find('div[class*="feed-shared-update"]');

        if(empty($posts)) {
            // Tentative 2: Chercher dans les articles
            $posts = $html->find('article');
        }

        if(empty($posts)) {
            // Tentative 3: Chercher les activity items
            $posts = $html->find('div[class*="activity"]');
        }

        $i = 0;
        foreach($posts as $post) {
            if($i++ > 20) break;

            // Extraire le texte du post
            $text_elem = $post->find('div[class*="commentary"], div[class*="feed-shared-text"], span[dir=ltr]', 0);
            $text = $text_elem ? $text_elem->plaintext : '';

            // Extraire le lien
            $link_elem = $post->find('a[href*="/feed/update/"]', 0);
            if(!$link_elem) {
                $link_elem = $post->find('a[href*="linkedin.com"]', 0);
            }
            $link = $link_elem ? 'https://www.linkedin.com' . $link_elem->href : $profile_url;

            // Nettoyer le lien
            if(strpos($link, 'linkedin.com') !== false && strpos($link, 'http') === false) {
                $link = 'https://www.linkedin.com' . $link;
            }

            // Extraire la date si disponible
            $date_elem = $post->find('time', 0);
            $date = $date_elem && isset($date_elem->datetime) ? date('r', strtotime($date_elem->datetime)) : date('r');

            if(!empty($text)) {
                $text = trim(clean_txt($text));
                $title = cutString($text, 0, 128);

                echo "    <item>
      <title>".htmlspecialchars($title, ENT_QUOTES, 'UTF-8')."</title>
      <description>".htmlspecialchars($text, ENT_QUOTES, 'UTF-8')."</description>
      <pubDate>".$date."</pubDate>
      <link>".$link."</link>
      <guid>".$link."_".$i."</guid>
    </item>
";
                $items_found = true;
            }
        }

        $html->clear();
        unset($html);
    }
}

// Si aucun item n'a été trouvé, créer un item d'information
if(!$items_found) {
    $now = date('r');

    // Vérifier si on a été redirigé vers le login
    $is_login_redirect = (strpos($html_content, 'checkpoint') !== false ||
                          strpos($html_content, 'S\'identifier') !== false ||
                          strpos($html_content, 'Sign in') !== false ||
                          $http_code == 401 || $http_code == 403);

    if($is_login_redirect) {
        $message = "⚠️ LinkedIn requiert une authentification pour accéder aux profils.

Solutions possibles :
1. Utiliser l'API officielle LinkedIn (https://developer.linkedin.com/)
2. Configurer un scraper avec authentification (cookies, session)
3. Utiliser un service tiers comme RSSHub ou Bridge

Pour l'instant, ce flux ne peut pas récupérer les posts automatiquement.
Visitez directement : ".$profile_url;
    } else {
        $message = "Aucun post trouvé pour ce profil LinkedIn.

Causes possibles :
- Le profil n'existe pas ou est privé
- Aucune activité récente publique
- Structure HTML de LinkedIn a changé

Visitez directement : ".$profile_url;
    }

    echo "    <item>
      <title>LinkedIn - ".$profile." (Configuration requise)</title>
      <description>".htmlspecialchars($message, ENT_QUOTES, 'UTF-8')."</description>
      <pubDate>".$now."</pubDate>
      <link>".$profile_url."</link>
      <guid>".$profile_url."_".time()."</guid>
    </item>
";
}

?>
  </channel>
</rss>
