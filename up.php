<?php
/**
 * RSS Feed Update Script
 * Fetches and updates RSS feeds using curl multi-handle for parallel requests
 */

include('/www/conf.php');
include('clean_text.php');

// Query performance monitoring helper
function logSlowQuery($queryName, $duration, $threshold = 100) {
    if ($duration > $threshold) {
        error_log(sprintf("SLOW QUERY [%s]: %.2fms (threshold: %dms)", $queryName, $duration, $threshold));
    }
}

// Allow CLI execution for cron jobs (updates all feeds)
if (php_sapi_name() === 'cli') {
    // CLI mode: bypass authentication, initialize session
    if (!isset($_SESSION)) {
        session_start();
    }
    $_SESSION['user_id'] = 1; // Default user for CLI
    if (!isset($_SESSION['mysqli'])) {
        $_SESSION['mysqli'] = $mysqli;
    }
}

// Security: Check authentication (web requests only)
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(403);
    die('Unauthorized access');
}

ini_set('max_execution_time', '500');
$mysqli = $_SESSION['mysqli'];

// Release session lock to allow other requests to proceed
session_write_close();

// Parse and validate parameters
$extra = '';
$feedId = null;
if(isset($_GET['id']) && is_numeric($_GET['id'])) {
    $feedId = (int)$_GET['id'];
    $extra = ' WHERE id = ' . $feedId;
}

$DEBUG = isset($_GET['debug']) ? 1 : 0;

// Debug mode: clean existing items for the feed
if($DEBUG && $feedId) {
    $mysqli->query("DELETE FROM reader_item WHERE id_flux = $feedId") or die($mysqli->error);
    $mysqli->query("DELETE FROM reader_user_item WHERE id_item NOT IN (SELECT id FROM reader_item)") or die($mysqli->error);
}

/**
 * Get RSS feed links from database
 */
function get_links($mysqli, $extra) {
    $query = 'SELECT id, rss FROM reader_flux' . $extra . ' ORDER BY RAND()';
    $result = $mysqli->query($query) or die($mysqli->error);
    return $result;
}

/**
 * Complete relative URLs with base URL
 */
function complete_link($link, $linkmaster) {
    if(isset($link) && !preg_match('/^https?:\/\//', $link)) {
        if(substr($link, 0, 1) == '/') {
            $pu = parse_url($linkmaster);
            if(!isset($pu['scheme'])) $pu['scheme'] = 'https';
            if(substr($link, 1, 1) == '/') {
                // Protocol-relative URL
                $link = $pu['scheme'] . ':' . $link;
            } else {
                // Absolute path
                $link = $pu['scheme'] . '://' . $pu['host'] . $link;
            }
        } else {
            // Relative path
            $link = rtrim($linkmaster, '/') . '/' . $link;
        }
    }
    return $link;
}

/**
 * Fetch YouTube video description using YouTube Data API v3
 * Requires YOUTUBE_API_KEY to be set in /www/conf.php
 * @param string $videoId YouTube video ID
 * @return string|null Video description or null if not found/no API key
 */
function get_youtube_description($videoId) {
    // Check if YouTube API key is configured
    if (!defined('YOUTUBE_API_KEY') || empty(YOUTUBE_API_KEY)) {
        return null;
    }

    $apiKey = YOUTUBE_API_KEY;
    $url = 'https://www.googleapis.com/youtube/v3/videos?part=snippet&id=' . urlencode($videoId) . '&key=' . urlencode($apiKey);

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200 || !$response) {
        return null;
    }

    $data = json_decode($response, true);

    if (isset($data['items'][0]['snippet']['description'])) {
        $description = $data['items'][0]['snippet']['description'];

        // Clean up and limit length
        $description = trim($description);
        if (strlen($description) > 2000) {
            $description = substr($description, 0, 2000) . '...';
        }

        return $description;
    }

    return null;
}

// Fetch feed URLs
$r = get_links($mysqli, $extra);
$mh = curl_multi_init();
$ch = array();
$feedIds = array();
$urlOrigins = array();
$i = 0;

// Initialize curl handles for parallel requests
while($d = $r->fetch_array()) {
    $ch[$i] = curl_init();
    curl_setopt_array($ch[$i], array(
        CURLOPT_URL => $d[1],
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => 'UTF-8',
        CURLOPT_SSL_VERIFYPEER => true,  // Security: Enable SSL verification
        CURLOPT_SSL_VERIFYHOST => 2,     // Security: Verify hostname
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3
    ));
    curl_multi_add_handle($mh, $ch[$i]);
    $feedIds[$i] = $d[0];
    $urlOrigins[$i] = $d[1];
    $i++;
}

// Execute all curl handles in parallel
do {
    $status = curl_multi_exec($mh, $active);
    if ($active) {
        curl_multi_select($mh);
    }
} while ($active && $status == CURLM_OK);

// FIX N+1: Fetch ALL feed metadata in ONE query instead of one per feed
$feedMetadata = [];
if ($i > 0) {
    $placeholders = implode(',', array_fill(0, $i, '?'));
    $stmt = $mysqli->prepare("SELECT id, link, title, rss FROM reader_flux WHERE id IN ($placeholders)");
    $types = str_repeat('i', $i);
    $stmt->bind_param($types, ...$feedIds);
    $query_start = microtime(true);
    $stmt->execute();
    $result = $stmt->get_result();
    logSlowQuery('up.php - batch feed metadata', (microtime(true) - $query_start) * 1000);
    while ($row = $result->fetch_assoc()) {
        $feedMetadata[$row['id']] = $row;
    }
}

// Process each feed
for($j = 0; $j < $i; $j++) {
    echo "\n" . $feedIds[$j] . " : ";

    // Get feed metadata from pre-loaded array
    if (!isset($feedMetadata[$feedIds[$j]])) {
        continue;
    }

    $feedMeta = $feedMetadata[$feedIds[$j]];

    // Parse XML content
    $xml = trim(curl_multi_getcontent($ch[$j]));

    // Clean XML: remove content after closing RSS tag, fix malformed URLs
    $xml = preg_replace('/^(.*<\/rss>).*$/s', '\\1', $xml);
    $xml = preg_replace('/url="(.*?\.(jpg|png|gif))\?.*?"/s', 'url="' . htmlspecialchars('\\1', ENT_XML1, 'UTF-8', true) . '"', $xml);
    $xml = preg_replace('/type=""/s', '', $xml);

    $rss = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

    // Update RSS URL if redirected
    $redirectURL = curl_getinfo($ch[$j], CURLINFO_EFFECTIVE_URL);
    if($urlOrigins[$j] != $redirectURL) {
        $stmt = $mysqli->prepare("UPDATE reader_flux SET rss = ? WHERE id = ?");
        $stmt->bind_param("si", $redirectURL, $feedIds[$j]);
        $stmt->execute();
    }

    // Handle XML parsing errors
    if (!$rss && $DEBUG) {
        foreach (libxml_get_errors() as $error) {
            var_dump($error);
        }
        echo '<pre>';
        print_r($rss);
        echo htmlspecialchars(curl_multi_getcontent($ch[$j]));
        echo '</pre>';
        libxml_clear_errors();
    }

    if(empty($rss)) {
        echo "Flux vide!<br />\n";
        continue;
    }

    // Extract feed title
    $title = (isset($rss->title)) ? $rss->title : $rss->channel->title;
    if(!isset($title)) {
        echo "Pas de titre!";
        if($DEBUG) {
            echo '<pre>';
            print_r($rss);
            echo '</pre>';
        }
        continue;
    }

    echo "$title : ";

    // Extract feed link
    $linkmaster = null;
    if($rss->channel->link) {
        $linkmaster = $rss->channel->link;
    } elseif($rss->link[0]['href']) {
        $linkmaster = $rss->link[0]['href'];
    }

    // Determine feed item structure
    if(isset($rss->channel->item)) {
        $flux = $rss->channel->item;
    } elseif(isset($rss->item)) {
        $flux = $rss->item;
    } elseif(isset($rss->entry)) {
        $flux = $rss->entry;
    } else {
        echo "$j : <b>/!\ Type de flux inconnu /!\</b><br />";
        if($DEBUG) print_r($rss);
        continue;
    }

    $nb_art = 0;

    // Process each item in the feed
    foreach ($flux as $item) {
        $link = null;

        // Limit to 5 articles per feed per update
        if($nb_art++ > 5) break;

        // Extract item link
        if(is_object($item->link)) {
            foreach($item->link as $t) {
                if($t['rel'] == "alternate" || $t['rel'] == "self") {
                    $link = $t['href'];
                }
                if(!isset($link) && isset($t['href'])) {
                    $link = $t['href'];
                }
            }
        }

        if(!isset($link) && isset($item->guid) && preg_match('/^https?:\/\//', $item->guid)) {
            $link = $item->guid;
        }
        if(!isset($link) && isset($item->link) && preg_match('/^https?:\/\//', $item->link)) {
            $link = $item->link;
        }

        // Complete relative URLs
        if(!preg_match('/^https?:\/\//', $linkmaster)) {
            $linkmaster = $feedMeta['link'];
        }

        $link = complete_link($link, $linkmaster);

        if(!isset($link) || !$link || $link == '') {
            if($DEBUG) {
                echo "Aucun lien trouvé.<br />";
                echo '<pre>';
                print_r($item);
                echo '</pre>';
            }
            continue;
        }

        // Use GUID as link if it's a URL
        $guid = null;
        if(isset($item->guid)) {
            $guid = $item->guid;
        }
        if(isset($guid) && preg_match('/^https?:\/\/.*/', $guid)) {
            $link = $guid;
        }

        if($DEBUG) {
            echo 'Lien : <a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a><br />';
        }

        // Clean link for database storage
        $link = str_replace(array(')', '(', '"', '\\'), array('', '', '', '\\\\'), $link);
        $link_without_protocol = preg_replace('/^https?/', '', $link);

        // Check if article already exists
        $stmt = $mysqli->prepare("SELECT id FROM reader_item WHERE id_flux = ? AND link LIKE ?");
        $searchPattern = '%' . $link_without_protocol . '%';
        $stmt->bind_param("is", $feedIds[$j], $searchPattern);
        $query_start = microtime(true);
        $stmt->execute();
        $existingResult = $stmt->get_result();
        logSlowQuery('up.php - check existing article', (microtime(true) - $query_start) * 1000, 50);

        if ($existingResult->num_rows == 0) {
            // New article - extract data
            $title = isset($item->title) ? $item->title : null;

            if(!$title) {
                echo "PAS DE TITRE !!!<br />";
                continue;
            }

            // Extract publication date
            $iDate = null;
            if(isset($item->pubDate)) {
                $iDate = $item->pubDate;
            } elseif(isset($item->published)) {
                $iDate = $item->published;
            }

            try {
                $date = new DateTime($iDate);
            } catch (Exception $e) {
                if($DEBUG) echo $e->getMessage();
                $date = new DateTime();
            }

            $iDate = $date->getTimestamp();
            if(!isset($iDate) || $iDate > time()) {
                $iDate = time();
            }

            // Extract content
            $content = null;
            if(isset($item->description)) {
                $content = $item->description;
            } elseif(isset($item->content)) {
                $content = $item->content;
            } elseif(isset($item->summary)) {
                $content = $item->summary;
            }

            // Check if content contains YouTube video or if link is YouTube
            $youtubeVideoId = null;
            if(preg_match('/^(.*\/\/)?(www\.)?youtube\.com\/(watch\?v=|shorts\/)([^&\s]+)/', $link, $m)) {
                $youtubeVideoId = $m[4];
            } elseif(preg_match('/^(.*\/\/)?youtu\.be\/([^&\s]+)/', $link, $m)) {
                $youtubeVideoId = $m[2];
            }

            // If YouTube video detected, add video embed and fetch description
            if($youtubeVideoId) {
                echo "Lien YouTube trouvé: " . $youtubeVideoId . "<br />";

                // Check if we already have this video in DB (description already fetched)
                $ytDescription = null;
                $needsFetch = true;

                $checkStmt = $mysqli->prepare("SELECT description FROM reader_item WHERE link = ? LIMIT 1");
                $checkStmt->bind_param("s", $link);
                $query_start = microtime(true);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                logSlowQuery('up.php - check YouTube cache', (microtime(true) - $query_start) * 1000, 50);
                if ($checkRow = $checkResult->fetch_assoc()) {
                    // Video already exists, description already in content
                    $needsFetch = false;
                    $content = $checkRow['description'];
                    echo "Vidéo déjà en base, description récupérée du cache<br />";
                }

                // Fetch YouTube description only for new videos
                if ($needsFetch) {
                    $ytDescription = get_youtube_description($youtubeVideoId);
                }

                // Build content only for new videos
                if ($needsFetch) {
                    $videoContent = '<yt>' . $youtubeVideoId . '</yt>';

                    if($ytDescription) {
                        echo "Description récupérée (" . strlen($ytDescription) . " chars)<br />";
                        // Add description below video
                        $videoContent .= '<div class="yt-description">' . nl2br(htmlspecialchars($ytDescription)) . '</div>';
                    }

                    // If there was existing content from RSS, append it
                    if(!empty($content)) {
                        $content = $videoContent . '<hr />' . $content;
                    } else {
                        $content = $videoContent;
                    }
                }
            } elseif(preg_match('/^(\/\/.*\.(jpe?g|gif|png))/', $link, $m)) {
                // Image link detected
                echo "Image trouvée!<br />";
                $content = '<img loading="lazy" decoding="async" src="' . htmlspecialchars($m[1]) . '" />';
            }

            if(!isset($content) || $content == '') {
                if($DEBUG) {
                    echo '<b>Pas de content</b><br/>';
                    print_r($item);
                    echo '<br /><br />';
                }
            }

            // Extract author
            $author = '';
            if(isset($item->author->name)) {
                $author = $item->author->name;
            } elseif(isset($item->author)) {
                $author = $item->author;
            }

            // Clean text content
            $title = clean_txt($title);
            $content = clean_txt($content);
            $author = clean_txt($author);

            // Clean and ensure GUID is not null (use link as fallback)
            if (isset($guid) && $guid) {
                $guid = clean_txt($guid);
            } else {
                $guid = $link; // Use link as GUID if no GUID provided
            }

            echo "MAJ<br />";

            // Insert new article (YouTube description already concatenated in $content)
            $stmt = $mysqli->prepare("
                INSERT INTO reader_item (id, id_flux, pubdate, guid, title, author, link, description)
                VALUES ('', ?, ?, ?, ?, ?, ?, ?)
            ");
            $pubdate = date("Y-m-d H:i:s", $iDate);
            $stmt->bind_param("issssss", $feedIds[$j], $pubdate, $guid, $title, $author, $link, $content);
            $query_start = microtime(true);
            $stmt->execute();
            logSlowQuery('up.php - insert article', (microtime(true) - $query_start) * 1000, 50);
        }

        echo ".";
    }

    // Update feed last update timestamp
    $stmt = $mysqli->prepare("UPDATE reader_flux SET `update` = CURRENT_TIMESTAMP() WHERE id = ?");
    $stmt->bind_param("i", $feedIds[$j]);
    $stmt->execute();

    echo "\n";
    curl_multi_remove_handle($mh, $ch[$j]);
}

curl_multi_close($mh);
?>
