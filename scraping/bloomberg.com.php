<?php

require('simple_html_dom.php');

$base_url = 'https://www.bloomberg.com/europe';
$site_name = 'Bloomberg';

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
if(!isset($_GET['debug'])) {
	header('Content-type: application/rss+xml; charset=utf-8');
}

echo '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
';
echo '    <atom:link href="'._get_URI().'" rel="self" type="application/rss+xml" />
';

echo "    <title>$site_name</title>
    <description>$site_name</description>
";
$url = $base_url;
echo "    <link>".$base_url."</link>
";

$html = shell_exec('wget  --header="Accept: text/html" --user-agent="Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:21.0) Gecko/20100101 Firefox/21.0" -O - "https://www.bloomberg.com"');
//file_get_html($url);
//  echo "<pre>";
// var_dump($html);
// die;
/*exec('/usr/bin/python /www/reader/scraping/cf.py "'.$url.'"', $htmla); //file_get_html($url);
var_dump($htmla);
die;
$html = implode('
	', $htmla);*/
$html = str_get_html($html);
/*var_dump($html);*/
//die;
$i = 0;
foreach($html->find('article a') as $element) {
	//if($i++ >= 2000) break;
	if(preg_match('/\/news\/articles\//', $element->href) || preg_match('/\/opinion\/articles\//', $element->href)) echo $element->href."<br />";
	//continue;
	$detail = shell_exec('wget  --header="Accept: text/html" --user-agent="Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:21.0) Gecko/20100101 Firefox/21.0" -O - "'.$base_url.$element->href.'"');
	 $detail = str_get_html($detail);
	// echo $detail;
	// die;
	foreach($detail->find('article h1') as $titre) {
		$mytitle = $titre->plaintext;
		break;
	}
	$mydescription='';
	foreach($detail->find('article p') as $info) {
		$mydescription .= $info->plaintext;
	}


	echo "    <item>
	      <title>",htmlspecialchars(stripslashes($mytitle),ENT_QUOTES,'UTF-8'),"</title>
	      <description>",(isset($mydescription)?htmlspecialchars(stripslashes($mydescription),ENT_QUOTES,'UTF-8'):""),"</description>
	      <link>".$element->href."</link>
	      <guid>".$element->href."</guid>
    </item>
";
die;
$detail->clear();
unset($detail);
}
?>
  </channel>
</rss>
