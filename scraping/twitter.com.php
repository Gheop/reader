<?php
require('simple_html_dom.php');
include('../clean_text.php');
$base_url = 'https://twitter.com';
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

header('Content-type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
';
echo '    <atom:link href="'._get_URI().'" rel="self" type="application/rss+xml" />
';
if(isset($_GET['f'])) $_POST['f'] = $_GET['f'];
if(!isset($_POST['f']) || empty($_POST['f'])) die("Pas de compte Twitter spécifié!");

$url = $base_url.'/'.$_POST['f'];
$html = file_get_html($url);
/*echo '<pre>';
print_r($html);
echo '</pre>';*/
foreach($html->find('p[class=ProfileHeaderCard-bio u-dir]') as $title_descr) {
$title_descr = $title_descr->plaintext;
}

echo "    <title>$site_name - ".$_POST['f']."</title>
    <description>".htmlspecialchars(stripslashes($title_descr),ENT_QUOTES,'UTF-8')."</description>
";

echo "    <link>"._get_URI()."</link>
";
$i = 0;
$tweet_block = $html->find('div[class=tweet]');
foreach($tweet_block as $tweet) {
	
	//var_dump($tweet);
	//die;
	if(isset($tweet->attr['data-retweet-id'])) continue;
	if($i++ > 19) break;
	$mylink = 'https://twitter.com/'.$_POST['f'].'/statuses/'.$tweet->attr['data-tweet-id'];

    // get tweet text
    $tweetText = $tweet->find('p[class=TweetTextSize]', 0)->plaintext;

    $tweetText = str_replace('&nbsp;', '', $tweetText);

    $tweetText2 = preg_replace('/\s*(pic.twitter.com\/.+)\s*/s','<img src="https://$1" />', $tweetText);
    $tweetText = preg_replace('/\s*(pic.twitter.com\/.+)\s*/s','', $tweetText);
//echo "<text>$tweetText</text>";
    //echo 'Tweet: ' . $tweetText . '<br/>';

    // get tweet stamp
    $tweetDate = $tweet->find('a[class=tweet-timestamp] span[class=_timestamp]', 0);
    //print_r($tweetDate);
    $date = date('r',$tweetDate->attr['data-time']);
    echo "    <item>
	      <title>",htmlspecialchars(stripslashes($tweetText),ENT_QUOTES,'UTF-8'),"</title>
	      <description>",(isset($tweetText2)?htmlspecialchars(stripslashes($tweetText2),ENT_QUOTES,'UTF-8'):""),"</description>
	      <pubDate>$date</pubDate>
	      <link>".(isset($mylink)?$mylink:"")."</link>
	      <guid>".(isset($mylink)?$mylink:"")."</guid>
    </item>
";

/*	var_dump($tweet);
	die;*/


$tweet->clear(); 
unset($tweet);

}
/*//TweetTextSize
foreach($html->find('div[class=tweet original-tweet]') as $element) {
	$mylink = 'https://twitter.com/'.$_POST['f'].'/statuses/'.$element->attr['data-tweet-id'];
	echo 
	//echo $element->innerhtml;
	$description = $element->find('p[class=TweetTextSize]');
	echo '<br />';
	echo '<pre>';
	//print_r($description->plaintext);
	echo '</pre>';
	die;
	//$link = 'https://twitter.com/'.$_POST['f'].'/statuses/'.$element->'data-tweet-id';
	if($i++ >= 20 ) break;
	$detail = file_get_html(file_url($element->data-tweet-id));
	foreach($detail->find('div[class=content-box-large box-with-header] tr td[class=tabledata0] a') as $lien) {
		if($lien->title != 'Download torrent file') continue;
		$mylink = $lien->href;
		break;
	}
	foreach($detail->find('div[class=panel-title] b') as $titre) {
		$mytitle = $titre->plaintext;
		break;
	}
	foreach($detail->find('div[id=description]') as $info) {
		$mydescription = $info->innertext;
	}
	$j = 0;
	foreach($detail->find('div[class=content-box-large box-with-header] tr td[class=tabledata0]') as $info) {
		$j++;
		if($j != 8) continue;
		if(isset($info->plaintext))
			$mytitle .= " (".$info->plaintext.")";
	}

	echo "    <item>
	      <title>",htmlspecialchars(stripslashes($mytitle),ENT_QUOTES,'UTF-8'),"</title>
	      <description>",(isset($mydescription)?htmlspecialchars(stripslashes($mydescription),ENT_QUOTES,'UTF-8'):""),"</description>
	      <pubDate>$date</pubDate>
	      <link>".(isset($mylink)?$mylink:"")."</link>
	      <guid>".(isset($mylink)?$mylink:"")."</guid>
    </item>
";
$element->clear(); 
unset($element);
}*/

?>
  </channel>
</rss>