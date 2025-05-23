<?php declare(strict_types=1);

if(isset($_GET['f'])) $_POST['f'] = $_GET['f'];
if(!isset($_POST['f']) || empty($_POST['f'])) die("Pas de compte Twitter spécifié!");
header('Content-type: application/rss+xml; charset=utf-8');
$ctx = stream_context_create(array('http'=>array('timeout' => 20),'https'=>array('timeout' => 20)));
$url = 'https://nitter.net/'.$_POST['f'].'/rss';
$r =  file_get_contents($url, false, $ctx);
$r = preg_replace('/<title>(.*)? \/ @(.*)?<\/title>/', "<title> $2</title>", $r);
$r = preg_replace('/https:\/\/nitter\.net\/pic\/pbs\.twimg\.com/', 'https://pbs.twimg.com', $r);
$r = preg_replace('/<link>https:\/\/nitter\.net/', '<link>https://twitter.com', $r);
/*
$r = preg_replace('/<title>(.*)?<\/title>\n?\r?<link>https:\/\/nitter\.net\/(.*)?<\/link>/si', "<title>$1</title>\n<link>https://twitter.com/$2</link>", $r);
*//*<title>Beetcoin / @Beetcoin</title>
    <link>https://nitter.net/Beetcoin</link>
*/
echo $r;
exit();


#https://gitlab.com/Daniel-KM/Omeka-S-module-BlockPlus/-/snippets/2068979



if(isset($_GET['f'])) $_POST['f'] = $_GET['f'];
if(!isset($_POST['f']) || empty($_POST['f'])) die("Pas de compte Twitter spécifié!");

function _get_URI() {
    return ($_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
}

$user = $_POST['f'];
$count = 20;

const URL_JS = 'https://abs.twimg.com/responsive-web/client-web/main.90f9e505.js';
const GRAPHQL_JS = 'https://twitter.com/i/api/graphql/ku_TJZNyXL2T4-D9Oypg7w/UserByScreenName';
const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64; rv:60.0) Gecko/20100101 Firefox/96.0';

// Get the authorization bearer from the browser.
$curl = curl_init(URL_JS);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$page = curl_exec($curl);
$matches = [];
preg_match('/s=\"AAAAA[^\"]+\"/', $page, $matches, PREG_OFFSET_CAPTURE);
$bearer = empty($matches[0][0]) ? null : substr($matches[0][0], 3, -1);
curl_close($curl);
if (!$bearer) {
    echo 'Scraper error: No authorization bearer';
    exit;
}

// Get the guest token.
$curl = curl_init('https://api.twitter.com/1.1/guest/activate.json');
curl_setopt($curl, CURLOPT_POST, 1);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'User-Agent: ' . USER_AGENT,
    'Content-Type: application/json',
    'Authorization: Bearer ' . $bearer,
]);
$page = curl_exec($curl);
$json_page = json_decode($page, true);
$token = $json_page['guest_token'] ?? null;
curl_close($curl);
if (!$token) {
    echo 'Scraper error: No guest token;';
    exit;
}

// Get the user ID here.            [
$query = ['variables' => json_encode([
    'screen_name' => (string) $user,
    'withHighlightedLabel' => true,
])];
$curl = curl_init(GRAPHQL_JS . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'User-Agent: ' . USER_AGENT,
    'Content-Type: application/json',
    'Authorization: Bearer ' . $bearer,
    'x-guest-token: ' . $token,
]);
$page = curl_exec($curl);
if (curl_errno($curl)) {
    echo 'Scraper error: ' . curl_error($curl);
    exit;
}
$page_json = json_decode($page, true);
$userId = $page_json['data']['user']['rest_id'];
curl_close($curl);

// Scrap the tweets.
$query = [
    'include_profile_interstitial_type' => 1,
    'include_blocking' => 1,
    'include_blocked_by' => 1,
    'include_followed_by' => 1,
    'include_want_retweets' => 0,
    'include_mute_edge' => 1,
    'include_can_dm' => 1,
    'include_can_media_tag' => 1,
    'skip_status' => 1,
    'cards_platform' => 'Web-12',
    'include_cards' => 1,
    'include_ext_alt_text' => 1,
    'include_quote_count' => 1,
    'include_reply_count' => 1,
    'tweet_mode' => 'extended',
    'include_entities' => 1,
    'include_user_entities' => 1,
    'include_ext_media_color' => 1,
    'include_ext_media_availability' => 1,
    'send_error_codes' => 1,
    'simple_quoted_tweet' => 1,
    'include_tweet_replies' => 0,
    'count' => $count,
    'userId' => $userId,
    'ext' => 'mediaStats,highlightedLabel',
];
$curl = curl_init("https://twitter.com/i/api/2/timeline/profile/$userId.json?" . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'User-Agent: ' . USER_AGENT,
    'Content-Type: application/json',
    'Authorization: Bearer ' . $bearer,
    'x-guest-token: ' . $token,
]);
$page = curl_exec($curl);
if (curl_errno($curl)) {
    echo 'Scraper error: ' . curl_error($curl);
    exit;
}
$json_page = json_decode($page, true);

$tweets = $json_page['globalObjects']['tweets'] ?? [];
if(isset($_GET['debug'])) {
echo '<pre>';
print_r($tweets);
// print_r([
//     'authorization_bearer' => $bearer,
//     'guest_token' => $token,
// ]);

}

include('../clean_text.php');

if(isset($tweets)) {
    if(!isset($_GET['debug'])) {
header('Content-type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>

';
echo '    <atom:link href="'._get_URI().'" rel="self" type="application/rss+xml" />
';
    echo "    <title> ".$user."</title>
    <description>Twitter de ".$user."</description>
";

echo "    <link>https://twitter.com/".$user."</link>
";
}
  
    foreach($tweets as $k => $v) {
        $img = '';
        if(isset($v['retweeted_status_id_str'])) continue;
        $date = date('r', strtotime($v['created_at']));
        //$date = date('r');
        if(isset($v['entities']['media'])) {
            foreach($v['entities']['media'] as $i) {
                if(isset($i['media_url_https']))
                    $img .= '<br /><img src="'.$i['media_url_https'].'" />';
            }
        }

        $description = $v['full_text'];
        $description = preg_replace('/@([^\s]*)/','<a href="https://twitter.com/$1">@$1</a>', $description);
        $description = preg_replace('/https:\/\/t.co\/([^\s]*)/','<a href="$0">@$0</a>', $description);

    echo "    <item>
          <title>",htmlspecialchars(cutString(stripslashes($v['full_text']), 0, 128),ENT_QUOTES,'UTF-8'),"</title>
          <description>",(isset($v['full_text'])?htmlspecialchars(stripslashes($description.$img),ENT_QUOTES,'UTF-8'):""),"</description>
          <pubDate>$date</pubDate>
          <link>https://twitter.com/".$user."/status/".$k."</link>
          <guid>https://twitter.com/".$user."/status/".$k."</guid>
    </item>
";

    }
} 
?>
  </channel>
</rss>

