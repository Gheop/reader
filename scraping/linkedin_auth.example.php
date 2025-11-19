<?php
/**
 * LinkedIn Scraper with Authentication
 *
 * Configuration requise :
 * 1. Copier ce fichier : cp linkedin_auth.example.php linkedin_auth.php
 * 2. Obtenir vos cookies LinkedIn après connexion :
 *    - Se connecter sur linkedin.com
 *    - Ouvrir les DevTools (F12) > Application > Cookies
 *    - Copier les valeurs de : li_at, JSESSIONID
 * 3. Remplir les constantes ci-dessous
 * 4. Modifier add_flux.php pour utiliser linkedin_auth.php au lieu de linkedin.com.php
 */

// CONFIGURATION - À remplir avec vos cookies LinkedIn
define('LINKEDIN_LI_AT', 'VOTRE_COOKIE_LI_AT_ICI');  // Cookie principal d'authentification
define('LINKEDIN_JSESSIONID', 'VOTRE_JSESSIONID_ICI'); // Session ID

// Note : Ces cookies expirent régulièrement, il faudra les mettre à jour

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

$profile = rtrim($profile, '/');
$profile_url = 'https://www.linkedin.com/in/' . $profile . '/';
$activities_url = 'https://www.linkedin.com/in/' . $profile . '/recent-activity/all/';

echo "    <title>LinkedIn - ".$profile."</title>
    <description>Derniers posts de ".$profile." sur LinkedIn</description>
    <link>".$profile_url."</link>
";

// Vérifier que les cookies sont configurés
if(!defined('LINKEDIN_LI_AT') || LINKEDIN_LI_AT === 'VOTRE_COOKIE_LI_AT_ICI') {
    $now = date('r');
    echo "    <item>
      <title>Configuration requise</title>
      <description>Les cookies LinkedIn ne sont pas configurés.
Voir le fichier scraping/linkedin_auth.example.php pour les instructions.</description>
      <pubDate>".$now."</pubDate>
      <link>".$profile_url."</link>
      <guid>".$profile_url."_config</guid>
    </item>
  </channel>
</rss>";
    exit;
}

// Requête avec authentification
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
    CURLOPT_COOKIE => 'li_at='.LINKEDIN_LI_AT.'; JSESSIONID="'.LINKEDIN_JSESSIONID.'"',
    CURLOPT_HTTPHEADER => array(
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: none',
        'Upgrade-Insecure-Requests: 1'
    )
));

$html_content = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if(isset($_GET['debug'])) {
    echo "<h2>Debug Mode - HTTP Code: $http_code</h2>";
    echo "<h3>First 5000 chars:</h3>";
    echo "<pre>" . htmlspecialchars(substr($html_content, 0, 5000)) . "</pre>";
    die();
}

$items_found = false;

if($html_content && $http_code == 200) {
    $html = str_get_html($html_content);

    if($html) {
        // Chercher les posts avec différents sélecteurs
        $posts = $html->find('div[class*="feed-shared-update"]');

        if(empty($posts)) {
            $posts = $html->find('div[data-urn*="activity"]');
        }

        $i = 0;
        foreach($posts as $post) {
            if($i++ > 20) break;

            // Texte du post
            $text_elem = $post->find('div[class*="commentary"], span[dir=ltr]', 0);
            $text = $text_elem ? trim($text_elem->plaintext) : '';

            // Lien
            $link_elem = $post->find('a[href*="/feed/update/"]', 0);
            $link = $link_elem ? 'https://www.linkedin.com' . $link_elem->href : $profile_url;

            // Date
            $date_elem = $post->find('time', 0);
            $date = $date_elem && isset($date_elem->datetime) ?
                    date('r', strtotime($date_elem->datetime)) : date('r');

            if(!empty($text) && strlen($text) > 10) {
                $text = clean_txt($text);
                $title = cutString($text, 0, 128);

                echo "    <item>
      <title>".htmlspecialchars($title, ENT_QUOTES, 'UTF-8')."</title>
      <description>".htmlspecialchars($text, ENT_QUOTES, 'UTF-8')."</description>
      <pubDate>".$date."</pubDate>
      <link>".$link."</link>
      <guid>".$link."</guid>
    </item>
";
                $items_found = true;
            }
        }

        $html->clear();
        unset($html);
    }
}

if(!$items_found) {
    $now = date('r');
    $is_auth_error = (strpos($html_content, 'checkpoint') !== false ||
                      $http_code == 401 || $http_code == 403);

    if($is_auth_error) {
        $msg = "Erreur d'authentification. Les cookies LinkedIn ont peut-être expiré.
Mettez-les à jour dans scraping/linkedin_auth.php";
    } else {
        $msg = "Aucun post trouvé ou structure HTML non reconnue.";
    }

    echo "    <item>
      <title>Pas de posts - ".$profile."</title>
      <description>".htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')."</description>
      <pubDate>".$now."</pubDate>
      <link>".$profile_url."</link>
      <guid>".$profile_url."_".time()."</guid>
    </item>
";
}

?>
  </channel>
</rss>
