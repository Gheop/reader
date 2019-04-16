<?php
require('simple_html_dom.php');
$url = 'https://decryptmedia.com/category/all';

function _get_URI() {
    if(isset($_SERVER['HTTPS']))
        return ($_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    return 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
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
echo "    <title>Decryptmedia</title>
    <description>Decryptmedia</description>
";
echo "    <link>"._get_URI()."</link>
";

$html = @file_get_html($url);
//var_dump($html);
//die;
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
foreach($html->find('li a') as $element) {
	if($i++ >= 10 ) break;
//	echo 'https://decryptmedia.com'.$element->href;
    $mylink = 'https://decryptmedia.com'.$element->href;
	$detail = @file_get_html($mylink);

    if(!$detail) break;
	foreach($detail->find('h1') as $titre) {
		$mytitle = $titre->plaintext;
		break;
	}
	foreach($detail->find('div[class^=Layout__ChildrenContainer]') as $info) {
		$mydescription = $info->innertext;
	}

	echo "    <item>
	      <title>",htmlspecialchars(stripslashes($mytitle),ENT_QUOTES,'UTF-8'),"</title>
	      <description>",(isset($mydescription)?htmlspecialchars(stripslashes($mydescription),ENT_QUOTES,'UTF-8'):""),"</description>
	      <link>$mylink</link>
	      <guid>$mylink</guid>
    </item>
";
$detail->clear();
unset($detail);
}
end:;
?>
  </channel>
</rss>
