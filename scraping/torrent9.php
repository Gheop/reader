<?php
require('simple_html_dom.php');
$base_url = 'http://www.torrent9.ch';
$uri = [
	'films' => '/torrents/films',
	'series' => '/torrents/'.urlencode('séries'),
	'musique' => '/torrents_musique.html',
	'ebook' => '/torrents_ebook.html',
	'logiciels' => '/torrents_logiciels.html',
	'jeux-pc' => '/torrents_jeux-pc.html',
	'jeux-consoles' => 'torrents_jeux-consoles.html'
];

/*function _is_curl() {
    return  (in_array  ('curl', get_loaded_extensions()))?true:false;
}
*/
function _get_URI() {
	return ($_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
}
/*
if(!_is_curl())  {
	echo "<b>Install and load <a href='http://php.net/manual/en/book.curl.php'>curl extension</a></b>";
	exit;
}*/

header('Content-type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
';
echo '    <atom:link href="'._get_URI().'" rel="self" type="application/rss+xml" />
';
if(isset($_GET['f'])) $_POST['f'] = $_GET['f'];
if(!isset($_POST['f']) || empty($_POST['f']) || !$uri[strtolower($_POST['f'])]) $_POST['f'] = 'films';

echo "    <title>Torrent9 - ".ucfirst($_POST['f'])."</title>
    <description>".ucfirst($_POST['f'])." de Torrent9</description>
";
$url = $base_url.$uri[strtolower($_POST['f'])];
echo "    <link>"._get_URI()."</link>
";
$html = @file_get_html($url);
if(!isset($html) || !$html || empty($html)) goto end;
/*exec('/usr/bin/python /www/reader/scraping/cf.py "'.$url.'"', $htmla); //file_get_html($url);
var_dump($htmla);
die;
$html = implode('
	', $htmla);
$html = str_get_html($html);
var_dump($html);*/
//die;
$i = 0;
foreach($html->find('td a') as $element) {
	if($i++ >= 10 ) break;
	$htmlaa = array();
      //echo $element->class. ' - '. $element->title. ' - '. $element->href . '<br>';
      exec('python /www/reader/scraping/cf.py "'.$base_url.$element->href.'"', $htmlaa);
      $detail = str_get_html(implode('
	', $htmlaa));
      if(!$detail) break;
	/*$detail = file_get_html($base_url.$element->href);*/
	foreach($detail->find('div[class=left-tab-section] a[class=btn btn-danger download]') as $lien) {
		$mylink = $lien->href;
		//$mylink = str_replace('http://protege-liens.net', '', $mylink);
		//$mylink = preg_replace('/.*get_torrent(.*)$/i','http://protege-liens.net/get_torrent\1', $mylink);
		break;
	}
	foreach($detail->find('h5') as $titre) {
		$mytitle = $titre->plaintext;
		break;
	}
	foreach($detail->find('div[class=movie-information]') as $info) {
		$mydescription = $info->last_child () ->innertext;
	}
	foreach($detail->find('div[class=movie-information] ul li',9) as $info) {
		if(isset($info->innertext))
			$mydescription .= "<br /><br />Poids : ".$info->last_child () ->innertext."<br />";
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
end:;
?>
  </channel>
</rss>
