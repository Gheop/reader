<?php
require('simple_html_dom.php');
$base_url = 'https://www.gktorrent.io';
$uri = [
	'films' => '/torrents/films', //'/torrents_films.html', //'/torrents/films',
	'series' => '/torrents/'.urlencode('séries'),
	'musique' => '/torrents_musique.html',
	'ebook' => '/torrents_ebook.html',
	'logiciels' => '/torrents_logiciels.html',
	'jeux-pc' => '/torrents_jeux-pc.html',
	'jeux-consoles' => 'torrents_jeux-consoles.html'
];

function _get_URI() {
    if(isset($_SERVER['HTTPS']))
        return ($_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    return 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
}

header('Content-type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <atom:link href="'._get_URI().'" rel="self" type="application/rss+xml" />
';

if(isset($_GET['f'])) $_POST['f'] = $_GET['f'];
if(!isset($_POST['f']) || empty($_POST['f']) || !$uri[strtolower($_POST['f'])]) $_POST['f'] = 'films';

echo "    <title>gktorrent - ".ucfirst($_POST['f'])."</title>
    <description>".ucfirst($_POST['f'])." de gktorrent</description>
";
$url = $base_url.$uri[strtolower($_POST['f'])];
echo "    <link>"._get_URI()."</link>
";
$html = file_get_html($url);

$i = 0;
foreach($html->find('td[class=liste-accueil-nom] a') as $element) {
	if($i++ >= 20 ) break;
	//echo $element->href;
	$detail = file_get_html($base_url.$element->href);
      if(!$detail) break;
	foreach($detail->find('div[class=title] a') as $title) {
		$mytitle = $title->plaintext;
		break;
	}
	foreach($detail->find('div[id=torrentsimage] img') as $img) {
		$mydescription = '<img src="'.$base_url.$img->src.'" /><br />';
		break;
	}
	foreach($detail->find('div[class=btn-download] a') as $link) {
		$mylink = $base_url.$link->href;
		break;
	}
	foreach($detail->find('div[class=torrentsdesc]') as $info) {
		$mydescription .= $info->innertext;
	}
	foreach($detail->find('table[class=table] tr td',4) as $info) {
		if(isset($info->innertext))
			$mydescription .= "<br /><br />".$info->plaintext."<br />";
	}
	echo "    <item>
	      <title>",htmlspecialchars(stripslashes($mytitle),ENT_QUOTES,'UTF-8'),"</title>
	      <description>",(isset($mydescription)?htmlspecialchars(stripslashes($mydescription),ENT_QUOTES,'UTF-8'):""),"</description>
	      <link>".(isset($mylink)?$mylink:"")."</link>
	      <guid>".(isset($mylink)?$mylink:"")."</guid>
    </item>
";
$detail->clear();
unset($detail);
}
?>
  </channel>
</rss>
