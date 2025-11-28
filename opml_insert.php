<?php
/**
 * OPML Feed Import
 * Security: Uses prepared statements, authentication, and XSS protection
 */
include(__DIR__ . '/conf.php');
include('clean_text.php');

// Security: Validate authentication
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(403);
    die('Vous devez être authentifié pour importer des flux.');
}

$userId = (int)$_SESSION['user_id'];

// Check if OPML file exists
if(!file_exists('opml.xml')) {
    exit('Failed to open opml.xml.');
}

$opml = simplexml_load_file('opml.xml');
if(!$opml) {
    exit('Failed to parse opml.xml.');
}

// Process each feed in OPML
foreach($opml->body->outline as $outline) {
    $title = $link = $description = $language = '';
    $rsslink = (string)$outline['xmlUrl'];
    $link = (string)$outline['htmlUrl'];

    echo "<b><u>" . htmlspecialchars($rsslink) . "</b></u> : <br />";

    // Fetch and parse RSS feed
    $rss = @simplexml_load_file($rsslink);
    if(!$rss) {
        echo 'Ne peut ouvrir ce flux.<br /><br />';
        continue;
    }

    // Extract feed metadata
    if($rss->channel->title) {
        $title = $rss->channel->title;
    } elseif($rss->title) {
        $title = $rss->title;
    }

    if($rss->channel->link) {
        $link = $rss->channel->link;
    } elseif($rss->link[0]['href']) {
        $link = $rss->link[0]['href'];
    }

    if($rss->channel->description) {
        $description = $rss->channel->description;
    } elseif($rss->subtitle) {
        $description = $rss->subtitle;
    }

    if($rss->channel->language) {
        $language = $rss->channel->language;
    } elseif($rss->language) {
        $language = $rss->language;
    }

    // Display extracted data
    if(isset($title) && $title) {
        echo htmlspecialchars($title) . "|" . htmlspecialchars($description) . "|" . htmlspecialchars($language) . "|" . htmlspecialchars($rsslink) . "|" . htmlspecialchars($link) . "<br />";
    }

    // Validate required fields
    if(!$title || !$rsslink || !$link) {
        echo 'Données manquantes pour insérer<br />';
        echo "<br /><br /><pre>";
        print_r($rss);
        echo "</pre><br />";
        continue;
    }

    // Clean text data
    $title = clean_txt($title);
    $description = clean_txt($description);
    $language = clean_txt($language);
    $rsslink = clean_txt($rsslink);
    $link = clean_txt($link);

    // Check if feed already exists
    $stmt = $mysqli->prepare("SELECT id FROM reader_flux WHERE rss = ?");
    $stmt->bind_param("s", $rsslink);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows == 0) {
        // Feed doesn't exist, create it
        echo "Pas trouvé !!!!<br />";

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
            echo "Ajouté.<br />";
        } else {
            echo "Error pour récupérer l'id<br />";
        }
    } else {
        echo "Ce flux existe déjà<br />";
    }

    echo "<br />";
}
?>
