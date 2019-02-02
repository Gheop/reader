<?php
require('simple_html_dom.php');
$base_url = 'http://www.legorafi.fr/';
$site_name = 'Le Gorafi';
$site_desc = "Toute l'information selon des sources contradictoires.";

/*function _is_curl() {
    return  (in_array  ('curl', get_loaded_extensions()))?true:false;
}
*/

//plus complet que htmtspecialchars_decode
function myhtmlspecialchars_decode($uSTR) {
	return strtr($uSTR, array_flip(get_html_translation_table(HTML_ENTITIES, ENT_QUOTES)));
}

function _get_URI() {
	return ($_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
}
/*
if(!_is_curl())  {
	echo "<b>Install and load <a href='http://php.net/manual/en/book.curl.php'>curl extension</a></b>";
	exit;
}*/
/*
header('Content-type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
';
echo '    <atom:link href="'._get_URI().'" rel="self" type="application/rss+xml" />
';

echo "    <title>$site_name</title>
    <description>$site_desc</description>
";
echo "    <link>".$base_url."</link>
";*/
$html = file_get_html($base_url);
// var_dump($html);
// die;

/*exec('/usr/bin/python /www/reader/scraping/cf.py "'.$url.'"', $htmla); //file_get_html($url);
var_dump($htmla);
die;
$html = implode('
	', $htmla);
$html = str_get_html($html);
var_dump($html);*/
//die;
$i = 0;
foreach($html->find('article a') as $element) {
	echo $element->href."<br />";
// 	if($i++ >= 20 ) break;
//  	$detail = file_get_html($element->href);
//  	foreach($detail->find('article a') as $titre) {
//  		$mytitle = $titre->plaintext;
//  		break;
//  	}
// 	foreach($detail->find('div[class=blog-post-content] p img') as $info) {
// 		$mydescription = '<img src="'.$info->src.'" />';
// 		break;
// 	}
// 	echo "    <item>
// 	      <title>",htmlspecialchars(stripslashes(myhtmlspecialchars_decode($mytitle)),ENT_QUOTES,'UTF-8'),"</title>
// 	      <description>",(isset($mydescription)?htmlspecialchars(stripslashes($mydescription),ENT_QUOTES,'UTF-8'):""),"</description>
// 	      <link>".$element->href."</link>
// 	      <guid>".$element->href."</guid>
//     </item>
// ";
// $detail->clear();
// unset($detail);
}
?>
  </channel>
</rss>
