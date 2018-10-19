<?php
require('simple_html_dom.php');
$base_url = 'http://subspace.club';
$site_name = 'Subspace Club';

function _get_URI() {
	return ($_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
}

header('Content-type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
';
echo '    <atom:link href="'._get_URI().'" rel="self" type="application/rss+xml" />
';

echo "    <title>$site_name</title>
    <description>Nouveautés cryptomonnaie, mining, trading, blockchain et technologie.</description>
";
$url = $base_url;
echo "    <link>".$base_url."</link>
";
$html = file_get_html($url);

$i = 0;
foreach($html->find('div[class=post_excerpt] a') as $element) {
	if($i++ >= 20 ) break;
	$detail = file_get_html($base_url.$element->href);
	if(!$detail) {break;}
	foreach($detail->find('h1') as $titre) {
		$mytitle = $titre->plaintext;
		break;
	}
	foreach($detail->find('div[class=post_contents]') as $info) {
		$mydescription = $info->innertext;
		$mydescription =str_replace('src="/', 'src="'.$base_url.'/', $mydescription);
	}
	echo "    <item>
	      <title>",htmlspecialchars(stripslashes($mytitle),ENT_QUOTES,'UTF-8'),"</title>
	      <description><![CDATA[",$mydescription,"]]></description>
	      <link>".$base_url.$element->href."</link>
	      <guid>".$base_url.$element->href."</guid>
    </item>
";
$detail->clear(); 
unset($detail);
}

?>
  </channel>
</rss>
