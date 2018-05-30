<?php
require('simple_html_dom.php');
$base_url = 'https://yggtorrent.com';
$site_name = 'yggtorrent';
$uri = [
	'films' => '/torrents/filmvideo/2183-film',
	'series' => '/torrents/filmvideo/2184-serie-tv',
	'musique' => '/torrents/audio/2148-musique',
	'livres' => '/torrents/ebook/2154-livres',
	'presse' => '/torrents/ebook/2156-presse'
];

function getCloudFlareFile($url) {
require_once 'libraries/httpProxyClass.php';
require_once 'libraries/cloudflareClass.php';

$httpProxy   = new httpProxy();
$httpProxyUA = 'proxyFactory';

$requestLink = $url;
$requestPage = json_decode($httpProxy->performRequest($requestLink));

// if page is protected by cloudflare
if($requestPage->status->http_code == 503) {
	// Make this the same user agent you use for other cURL requests in your app
	cloudflare::useUserAgent($httpProxyUA);
	
	// attempt to get clearance cookie	
	if($clearanceCookie = cloudflare::bypass($requestLink)) {
		// use clearance cookie to bypass page
		$requestPage = $httpProxy->performRequest($requestLink, 'GET', null, array(
			'cookies' => $clearanceCookie
		));
		// return real page content for site
		$requestPage = json_decode($requestPage);
		return $requestPage;
	//	echo $requestPage->content;
	} else {
		// could not fetch clearance cookie
		echo 'Could not fetch CloudFlare clearance cookie (most likely due to excessive requests)';
	}	
}

}
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
if(!isset($_POST['f']) || empty($_POST['f']) || !$uri[strtolower($_POST['f'])]) $_POST['f'] = 'films';

echo "    <title>$site_name - ".ucfirst($_POST['f'])."</title>
    <description>".ucfirst($_POST['f'])." de $site_name</description>
";
$url = $base_url.$uri[strtolower($_POST['f'])];
echo "    <link>"._get_URI()."</link>
";

$html = file_get_html($url);
//$html = str_get_html(getCloudFlareFile($url)->content);
$i = 0;
foreach($html->find('td a[class=torrent-name]') as $element) {
	if($i++ >= 20 ) break;
/*	echo $element->href."<br /><pre>";
	echo file_get_html(file_url($element->href));
	echo "</pre><br /><br /><br /><br /><br /><br /><br /><hr />";
	die;
*/
	$detail = file_get_html(file_url($element->href));
//	$detail = str_get_html(getCloudFlareFile(file_url($element->href))->content);
/*	echo '<pre>';
	var_dump($detail);
	echo '</pre>';
	die;*/
	foreach ($detail->find('a[class=btn btn-primary]') as $mylinktmp) {
		$mylink = $mylinktmp->href;
		break;
		# code...
	}
/*	$mylink = $detail->find('a[class=btn btn-success]');
	var_dump($mylink->href);
	die;*/
	foreach ($detail->find('div[class=panel-title] b') as $mytitletmp) {
		$mytitle = $mytitletmp->plaintext;
		break;
	}
	foreach($detail->find('div[id=description]') as $mydescriptiontmp) {
		$mydescription = $mydescriptiontmp->innertext;
		break;
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
