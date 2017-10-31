<?php
require('simple_html_dom.php');
$base_url = 'https://www.leboncoin.fr';
$site_name = 'leboncoin';

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
$_GET['f'] = 'https://www.leboncoin.fr/annonces/offres/poitou_charentes/?th=1';
if(isset($_GET['f'])) $_POST['f'] = $_GET['f'];
if(!isset($_POST['f']) || empty($_POST['f'])) die;


$html = file_get_html($_POST['f']);
foreach($html->find('h1[class=grey small no-border]') as $element) {
    $title = utf8_decode($element->innertext);
	break;
}

echo "    <title>$site_name - ".htmlspecialchars(stripslashes($title),ENT_QUOTES,'UTF-8')."</title>
    <description>$site_name - ".htmlspecialchars(stripslashes($title),ENT_QUOTES,'UTF-8')."</description>
";
echo "    <link>"._get_URI()."</link>
";
$i = 0;
foreach($html->find('a[class=list_item clearfix trackable]') as $element) {
	if($i++ >= 10 ) break;
	$mytitle = $element->title;
	$mylink = 'https:'.$element->href;
	$detail = file_get_html($mylink);
	$mydescription = '';
	foreach ($detail->find('*[class=property],*[class=value]') as $mydescriptiontmp) {
		//$mydescription .= '<b>'.($mydescriptiontmp->itemprop?$mydescriptiontmp->itemprop:'Prix').'</b><br />'.$mydescriptiontmp->innertext.'<br />';
$mydescription .= $mydescriptiontmp->innertext.'<br />';
	}
	echo "    <item>
	      <title>",htmlspecialchars(stripslashes($mytitle),ENT_QUOTES,'UTF-8'),"</title>
	      <description>",(isset($mydescription)?htmlspecialchars(stripslashes($mydescription),ENT_QUOTES,'UTF-8'):""),"</description>
	      <link>".(isset($mylink)?$mylink:"")."</link>
	      <guid>".(isset($mylink)?$mylink:"")."</guid>
    </item>
";
//die;
$detail->clear(); 
unset($detail);
}

?>
  </channel>
</rss>
