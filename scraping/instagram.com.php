<?php
require('simple_html_dom.php');
include('../clean_text.php');
$base_url = 'https://www.picuki.com/profile/';
$site_name = 'Instagram';

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
if(!isset($_POST['f']) || empty($_POST['f'])) die("Pas de compte Instagram spécifié!");

$url = $base_url.'/'.$_POST['f'];
$html = file_get_html($url);
/*echo '<pre>';
echo $html;
echo '</pre>';
die;*/

foreach($html->find('div[class=profile-description]') as $title_descr) {
$title_descr = $title_descr->plaintext;
}

echo "    <title> ".$_POST['f']."</title>
    <description>".htmlspecialchars(stripslashes($title_descr),ENT_QUOTES,'UTF-8')."</description>
";

echo "    <link>https://www.instagram.com/".$_POST['f']."/</link>
";
$i = 0;
die;
$tweet_block = $html->find('div[class="timeline"] table[class=tweet]');
foreach($tweet_block as $tweet) {
	if($i++ > 19) break;

    // get tweet text
    $tweetText = $tweet->find('div[class=tweet-text] div', 0)->plaintext;
    $mylink =  'https://twitter.com'.$tweet->find('td[class=timestamp] a',0)->attr['href'];

    $tweetText = str_replace('&nbsp;', '', $tweetText);
   // $tweetText2 = preg_replace('/@([^\s]*)/','<a href="https://twitter.com/$1">@$1</a>', $tweetText);
    //$tweetText2 = preg_replace('/\#([^\s]*)/','<a href="https://twitter.com/hashtag/$1">@$1</a>', $tweetText2);
   //// $tweetText2 = preg_replace('/\s*(pic.twitter.com\/.+)\s*/s','<img src="https://$1" />', $tweetText);
    //$tweetText2 = preg_replace('/\s*(pic.twitter.com\/.+)\s*/s','', $tweetText);
    //$tweetText = preg_replace('/\s*(pic.twitter.com\/.+)\s*/s','', $tweetText);
    //$tweetPic = $tweet->find('div[class=AdaptiveMedia-photoContainer js-adaptive-photo]', 0);

    //if(isset($tweetPic->attr["data-image-url"]) && $tweetPic->attr["data-image-url"] != '/')
    //	$tweetText2 = $tweetText.'<br /><img src="'.$tweetPic->attr["data-image-url"].'" />';

    $date = date('r');

    echo "    <item>
	      <title>",htmlspecialchars(cutString(stripslashes($tweetText), 0, 128),ENT_QUOTES,'UTF-8'),"</title>
	      <description>",(isset($tweetText)?$tweetText:""),"</description>
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
