<?php
/**
 * Add RSS Feed
 * Allows authenticated users to subscribe to RSS/Atom feeds
 * Supports special site detection (YouTube, Twitter, Reddit, etc.)
 */

error_reporting(E_ALL);
include(__DIR__ . '/conf.php');
include('scraping/simple_html_dom.php');
include('clean_text.php');

// Security: Validate user authentication
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(403);
    die('Vous devez être authentifié pour ajouter un flux.');
}

$userId = (int)$_SESSION['user_id'];
$debug = false;
$USERAGENT = 'Mozilla/5.0 (compatible; GheopReader/1.0; +https://reader.gheop.com/)';

/**
 * Fetch URL content with security enabled
 */
function getContentUrl($url) {
    global $USERAGENT;

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_USERAGENT => $USERAGENT,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => 'UTF-8',
        CURLOPT_SSL_VERIFYPEER => true,  // Security: Enable SSL verification
        CURLOPT_SSL_VERIFYHOST => 2,     // Security: Verify hostname
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3
    ));

    $result = curl_exec($ch);
    curl_close($ch);

    return $result ? trim($result) : false;
}

/**
 * Check if content is valid RSS/Atom feed
 */
function isRSSContent($content) {
    if(!$content) return false;

    try {
        $rss = @new SimpleXmlElement($content);
    } catch(Exception $e) {
        return false;
    }

    return (
        (isset($rss->channel->item) && $rss->channel->item->count() > 0) ||
        (isset($rss->item) && $rss->item->count() > 0) ||
        (isset($rss->entry) && $rss->entry->count() > 0)
    );
}

/**
 * Detect RSS feed URL for special sites
 */
function searchRSSUrlSpecialSite($url) {
    // YouTube channel
    if(preg_match('/^.*\/\/www\.youtube\.com\/channel\/(.*)$/', $url, $m)) {
        return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $m[1];
    }

    // YouTube user
    if(preg_match('/^.*\/\/www\.youtube\.com\/user\/([^\?\&\/]*)(.*)$/', $url, $m)) {
        $html = file_get_html($url);
        if($html) {
            foreach($html->find('meta[itemprop=channelId]') as $element) {
                return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $element->content;
            }
        }
        return false;
    }

    // YouTube video (extract channel using YouTube API)
    if(preg_match('/^.*\/\/(www\.|m\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com)\/(watch\?.*\&?v=|embed\/|shorts\/)?([^\?\&]*)(.*)$/', $url, $m)) {
        $videoId = $m[4];

        // Use YouTube API if available
        if(defined('YOUTUBE_API_KEY') && !empty(YOUTUBE_API_KEY)) {
            $apiKey = YOUTUBE_API_KEY;
            $apiUrl = 'https://www.googleapis.com/youtube/v3/videos?part=snippet&id=' . urlencode($videoId) . '&key=' . urlencode($apiKey);

            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $apiUrl,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ));

            $response = curl_exec($ch);
            curl_close($ch);

            if($response) {
                $data = json_decode($response, true);
                if(isset($data['items'][0]['snippet']['channelId'])) {
                    $channelId = $data['items'][0]['snippet']['channelId'];
                    return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $channelId;
                }
            }
        }

        // Fallback: Try HTML scraping (old method, may not work)
        $html = file_get_html('https://www.youtube.com/watch?v=' . $videoId);
        if($html) {
            foreach($html->find('meta[itemprop=channelId]') as $element) {
                return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $element->content;
            }
        }
        return false;
    }

    // Dailymotion short URL
    if(preg_match('/^.*dai\.ly\/(.*)$/', $url, $m)) {
        return searchRSSUrlSpecialSite('http://www.dailymotion.com/video/' . $m[1]);
    }

    // Dailymotion video (extract user)
    if(preg_match('/^.*\/\/(www\.)?dailymotion\.com\/(embed\/)?video\/([^\?]*)(.*)?$/', $url, $m)) {
        $html = file_get_html('http://www.dailymotion.com/video/' . $m[3]);
        if($html) {
            foreach($html->find('meta[property=video:director]') as $element) {
                return searchRSSUrlSpecialSite($element->content);
            }
        }
        return false;
    }

    // Dailymotion user
    if(preg_match('/^.*\/\/www\.dailymotion\.com\/([^\/\?].*)$/', $url, $m)) {
        return 'http://www.dailymotion.com/rss/user/' . $m[1];
    }

    // Twitter/X
    if(preg_match('/^.*\/\/(www\.)?(twitter\.com|x\.com)\/([^\/\?]+)(\/.*)?$/', $url, $m)) {
        // Exclure les pages spéciales (home, explore, notifications, messages, search)
        $excluded = ['home', 'explore', 'notifications', 'messages', 'search', 'i', 'settings'];
        if (!in_array(strtolower($m[3]), $excluded)) {
            return 'https://reader.gheop.com/scraping/twitter.com.php?f=' . urlencode($m[3]);
        }
    }

    // Reddit
    if(preg_match('/^.*\/\/(www\.)?reddit\.com\/(r|u)\/([^\/\?]+)(\/.*)?$/', $url, $m)) {
        // URL de subreddit: https://www.reddit.com/r/programming/
        // URL d'utilisateur: https://www.reddit.com/u/username/
        return 'https://reader.gheop.com/scraping/reddit.com.php?f=' . urlencode($m[2] . '/' . $m[3]);
    }

    // Medium
    if(preg_match('/^.*\/\/(.*)\.medium\.com\/(.*)?$/', $url, $m)) {
        if(isset($m[1]) && $m[1] != 'www') {
            return 'https://' . $m[1] . '.medium.com/feed';
        }
        return false;
    }

    // LinkedIn
    if(preg_match('/^.*\/\/(www\.)?linkedin\.com\/in\/([^\/\?]+)(\/.*)?$/', $url, $m)) {
        return 'https://reader.gheop.com/scraping/linkedin.com.php?f=' . urlencode($m[2]);
    }

    // Instagram
    // Gérer différents formats d'URLs Instagram
    if(preg_match('/^.*\/\/(www\.)?instagram\.com\/stories\/([^\/\?]+)(\/.*)?$/', $url, $m)) {
        // URL de stories: https://www.instagram.com/stories/username/
        return 'https://reader.gheop.com/scraping/instagram.com.php?f=' . urlencode($m[2]);
    }
    if(preg_match('/^.*\/\/(www\.)?instagram\.com\/([^\/\?]+)(\/.*)?$/', $url, $m)) {
        // URL de profil standard: https://www.instagram.com/username/
        // Ne pas matcher les URLs de posts (/p/) ou reels (/reel/)
        if($m[2] !== 'p' && $m[2] !== 'reel' && $m[2] !== 'tv' && $m[2] !== 'stories') {
            return 'https://reader.gheop.com/scraping/instagram.com.php?f=' . urlencode($m[2]);
        }
    }

    // TikTok
    // Gérer différents formats d'URLs TikTok
    if(preg_match('/^.*\/\/(www\.)?tiktok\.com\/@([^\/\?]+)(\/.*)?$/', $url, $m)) {
        // URL de profil: https://www.tiktok.com/@username
        // URL de vidéo: https://www.tiktok.com/@username/video/123456789
        return 'https://reader.gheop.com/scraping/tiktok.com.php?f=' . urlencode('@' . $m[2]);
    }

    return false;
}

/**
 * Search for RSS feed URL in HTML page
 */
function searchRSSUrl($url) {
    // Check special sites first
    if($urlfound = searchRSSUrlSpecialSite($url)) {
        return $urlfound;
    }

    $doc = new DOMDocument();
    $doc->strictErrorChecking = false;
    libxml_use_internal_errors(true);

    $content = getContentUrl($url);
    if(!$content) return false;

    $doc->loadHTML($content);
    libxml_clear_errors();

    if(!$xml = simplexml_import_dom($doc)) return false;

    // Look for RSS/Atom links in HTML head
    $xpath_results = $xml->xpath('//link[@rel="alternate"]');
    foreach ($xpath_results as $node) {
        if($node['type'] == 'application/rss+xml' || $node['type'] == 'application/atom+xml') {
            $href = (string)$node['href'];

            // Complete relative URLs
            if(substr($href, 0, 1) == '/') {
                $p = parse_url($url);
                $ret = $p['scheme'] . '://' . $p['host'];
                if(isset($p['port'])) $ret .= ':' . $p['port'];
                $ret .= $href;
                return $ret;
            }

            return $href;
        }
    }

    return false;
}

/**
 * Get valid RSS feed URL from any URL
 */
function getRSSLink($url) {
    $content = getContentUrl($url);

    if(!$content) {
        echo "<br />La page " . htmlspecialchars($url) . " n'a pas de contenu.<br />";
        return false;
    }

    // Check if URL is already RSS
    if(isRSSContent($content)) {
        return $url;
    }

    // Search for RSS link in page
    $rssUrl = searchRSSUrl($url);
    if($rssUrl) {
        $rssContent = getContentUrl($rssUrl);
        if(isRSSContent($rssContent)) {
            return $rssUrl;
        }
    }

    return false;
}

/**
 * Validate and sanitize URL
 */
function validate_url($url) {
    if(!preg_match('/^https?:\/\//', $url)) {
        $url = '//' . $url;
    }

    $url = filter_var($url, FILTER_SANITIZE_URL);

    if(filter_var($url, FILTER_VALIDATE_URL) !== false) {
        // Security: Only allow HTTP/HTTPS protocols
        $parsed = parse_url($url);
        if(isset($parsed['scheme']) && !in_array($parsed['scheme'], array('http', 'https'))) {
            return false;
        }
        return $url;
    }

    return false;
}

// Get RSS link from POST/GET parameters
$rsslink = null;
if(isset($_POST['link'])) {
    $rsslink = $_POST['link'];
} elseif(isset($_GET['f'])) {
    $rsslink = $_GET['f'];
} elseif(isset($_GET['feed_url'])) {
    $rsslink = $_GET['feed_url'];
} else {
    echo "Pas de lien trouvé";
    exit;
}

// Twitter shorthand (@username or #hashtag)
if(preg_match('/^[@#](.*)$/', $rsslink, $m)) {
    echo "Twitter: " . htmlspecialchars($m[1]) . "<br />";
    $rsslink = 'https://reader.gheop.com/scraping/twitter.com.php?f=' . urlencode($m[1]);
}

// Validate URL
if(!$rsslink = validate_url($rsslink)) {
    echo "Ce lien n'est pas valide.";
    exit;
}

// Find RSS feed URL
if(!$rsslink = getRSSLink($rsslink)) {
    echo "Ce site n'a pas de flux RSS.";
    exit;
}

// Parse RSS feed
$page = getContentUrl($rsslink);
if(!$rss = @simplexml_load_string($page)) {
    echo "Ce flux n'est pas actif pour le moment.";
    exit;
}

// Extract feed metadata
$title = '';
if($rss->channel->title) {
    $title = $rss->channel->title;
} elseif($rss->title) {
    $title = $rss->title;
}

$link = '';
if($rss->channel->link) {
    $link = $rss->channel->link;
} elseif($rss->link[0]['href']) {
    $link = $rss->link[0]['href'];
}

// Complete relative link URLs
if(isset($link) && !preg_match('/^https?:\/\//', $link)) {
    $pu = parse_url($rsslink);
    if(!isset($pu['scheme'])) $pu['scheme'] = 'https';
    $link = $pu['scheme'] . '://' . $pu['host'] . $link;
}

$description = '';
if($rss->channel->description) {
    $description = $rss->channel->description;
} elseif($rss->subtitle) {
    $description = $rss->subtitle;
}

$language = '';
if($rss->channel->language) {
    $language = $rss->channel->language;
} elseif($rss->language) {
    $language = $rss->language;
}

// Validate required fields
if(!$title || !$rsslink || !$link) {
    if($debug) {
        echo "Données manquantes pour insérer<br />";
        echo "<pre>";
        print_r($rss);
        echo "</pre>";
    } else {
        echo "Impossible d'extraire les informations du flux.";
    }
    exit;
}

// Clean text data
$title = clean_txt($title);
$rsslink = clean_txt($rsslink);
$language = clean_txt($language);
$link = clean_txt($link);
$description = clean_txt($description);

// Check if feed already exists
$stmt = $mysqli->prepare("SELECT id FROM reader_flux WHERE rss = ?");
$stmt->bind_param("s", $rsslink);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    // Feed doesn't exist, create it

    // Add space prefix for certain feeds (sorting hack)
    if(preg_match('/^https:\/\/github\.com\/.*/', $rsslink)) {
        $title = ' ' . $title;
    }
    if(preg_match('/^https?:\/\/(.*)?youtube\.com\/.*/', $rsslink)) {
        $title = ' ' . $title;
    }

    $stmt = $mysqli->prepare("
        INSERT INTO reader_flux (title, description, language, rss, link)
        VALUES (?, ?, ?, ?, ?)
    ");
    $titleNl2br = nl2br(trim($title));
    $descNl2br = nl2br(trim($description));
    $stmt->bind_param("sssss", $titleNl2br, $descNl2br, $language, $rsslink, $link);
    $stmt->execute();

    $feedId = $mysqli->insert_id;

    if($feedId) {
        // Subscribe user to feed
        $stmt = $mysqli->prepare("INSERT INTO reader_user_flux (id_user, id_flux) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $feedId);
        $stmt->execute();

        // Fetch initial articles
        getContentUrl('https://reader.gheop.com/up_parallel.php?id=' . $feedId);

        echo "Vous êtes maintenant inscrit à ce flux.";
    } else {
        echo "Erreur lors de la création du flux.";
    }
} else {
    // Feed exists, check if user is already subscribed
    $feedRow = $result->fetch_assoc();
    $feedId = $feedRow['id'];

    $stmt = $mysqli->prepare("SELECT id FROM reader_user_flux WHERE id_flux = ? AND id_user = ?");
    $stmt->bind_param("ii", $feedId, $userId);
    $stmt->execute();
    $subResult = $stmt->get_result();

    if($subResult->num_rows == 0) {
        // User not subscribed, subscribe them
        $stmt = $mysqli->prepare("INSERT INTO reader_user_flux (id_user, id_flux) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $feedId);
        $stmt->execute();

        // Fetch initial articles
        getContentUrl('https://reader.gheop.com/up_parallel.php?id=' . $feedId);

        echo "Vous êtes maintenant inscrit à ce flux.";
    } else {
        echo "Vous êtes déjà inscrit à ce flux.";
    }
}
?>
