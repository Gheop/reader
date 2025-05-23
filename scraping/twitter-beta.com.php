<?php
require('simple_html_dom.php');
include('../clean_text.php');
$base_url = 'https://mobile.twitter.com';
$site_name = 'Twitter';

function _get_URI() {
	return ($_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
}

function file_url($url){
  $parts = parse_url($url);
  $path_parts = array_map('rawurldecode', explode('/', $parts['path']));

  return
    $parts['scheme'] . '://' .
    $parts['host'] .
    implode('/', array_map('rawurlencode', $path_parts))
  ;
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
if(!isset($_POST['f']) || empty($_POST['f'])) die("Pas de compte Twitter spécifié!");

$url = $base_url.'/'.$_POST['f'];

$timeout = 10;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
if (preg_match('`^https://`i', $url))
{
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
}
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Définition du header "User-Agent:"
// Simulation d'un Firefox 3.6.13
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
$page_content = curl_exec($ch);
curl_close($ch);
//echo $page_content;
/*die;*/
$html =  str_get_html($page_content);
//$html = file_get_html($url);
/*echo '<pre>';*/
//echo $html;
/*echo '</pre>';*/
/*die;*/

$mytitle = '';
foreach($html->find('div[data-testid*="UserDescription"]') as $title_descr) {
  if($title_descr->plaintext)
    $mytitle = trim($title_descr->plaintext);
}

echo "    <title> ".$_POST['f']."</title>
    <description>".htmlspecialchars(stripslashes($mytitle),ENT_QUOTES,'UTF-8')."</description>
";

echo "    <link>https://twitter.com/".$_POST['f']."</link>
";
$i = 0;

$tweet_block = $html->find('article[data-testid*="tweet"]');
/*echo '<pre>';
print_r($tweet_block[0]);
echo '</pre>';*/
foreach($tweet_block as $tweet) {
 // echo 'PROOT!';
 // echo $tweet;
	if($i++ > 19) break;

    // get tweet text
    $tweetText = $tweet->find('div[itemprop*="articleBody"]',0)->plaintext;
    $mylink =  'https://twitter.com'.$tweet->find('a[role="link"]',3)->attr['href'];
    $mydate = $tweet->find('time',0)->attr['datetime'];
    $tweetText = str_replace('&nbsp;', '', $tweetText);
   //  pic.twitter.com/qVu97Ws4Fn 
   // $tweetText2 = preg_replace('/@([^\s]*)/','<a href="https://twitter.com/$1">@$1</a>', $tweetText);
    //$tweetText2 = preg_replace('/\#([^\s]*)/','<a href="https://twitter.com/hashtag/$1">@$1</a>', $tweetText2);
   //// $tweetText2 = preg_replace('/\s*(pic.twitter.com\/.+)\s*/s','<img src="https://$1" />', $tweetText);
    //$tweetText2 = preg_replace('/\s*(pic.twitter.com\/.+)\s*/s','', $tweetText);
    //$tweetText = preg_replace('/\s*(pic.twitter.com\/.+)\s*/s','', $tweetText);
    //$tweetPic = $tweet->find('div[class=AdaptiveMedia-photoContainer js-adaptive-photo]', 0);

$tweetText2 = preg_replace('/\s*(pic.twitter.com\/.+)\s*/s','<img src="https://$1" />', $tweetText);
    //if(isset($tweetPic->attr["data-image-url"]) && $tweetPic->attr["data-image-url"] != '/')
    //	$tweetText2 = $tweetText.'<br /><img src="'.$tweetPic->attr["data-image-url"].'" />';

    $date = date('r', strtotime($mydate));
    //$date = $mydate;


//htmlspecialchars(cutString(stripslashes($tweetText), 0, 128),ENT_QUOTES,'UTF-8'),
    echo "    <item>
	      <title>",cutString(stripslashes($tweetText), 0, 128),"</title>
	      <description>",(isset($tweetText2)?$tweetText2:""),"</description>
	      <pubDate>$date</pubDate>
	      <link>".(isset($mylink)?$mylink:"")."</link>
	      <guid>".(isset($mylink)?$mylink:"")."</guid>
    </item>
";
//die;	      <description>",(isset($tweetText2)?htmlspecialchars(stripslashes($tweetText2),ENT_QUOTES,'UTF-8'):""),"</description>
/*	var_dump($tweet);
	die;*/


$tweet->clear();
unset($tweet);

}

?>
  </channel>
</rss>
