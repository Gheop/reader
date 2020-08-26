<?php
require('simple_html_dom.php');
$url = 'https://realt.co/marketplace/';

function _get_URI() {
    if(isset($_SERVER['HTTPS']))
        return ($_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    return 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
}
if(!isset($_GET['debug'])) {
header('Content-type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <atom:link href="'._get_URI().'" rel="self" type="application/rss+xml" />
';

echo "    <title>RealT - Marketplace</title>
    <description>Disponibilité à la vente</description>
";
echo "    <link>"._get_URI()."</link>
";
}
$pages = [1];
$i = 0;
function get_infos($page) {
	global $url;
	$html = file_get_html($url.'?product-page='.$page);
	if(!$html) return;
	foreach($html->find('ul[class=products columns-1] li') as $property) {

		foreach($property->find('h2[class=woocommerce-loop-product__title]') as $token) {
			$mytoken = $token->plaintext;
			break;
		}
		foreach($property->find('div[class=woocommerce-shop-loop right-col]') as $details) {
			$mydetails =  $details->innertext;
			break;
		}
		foreach($property->find("div[class=property_status-banner] span") as $status) {
			$mystatus = trim($status->plaintext);
			//$mytoken .= $mystatus;
			break;
		}
		foreach($property->find('a[class=single_add_to_cart_button button alt realTbtn]') as $link) {
			$mylink = $link->href;
			break;
		}
		foreach($html->find('a[class=next page-numbers]') as $next) {
			$mynext = explode("=",$next->href);
		}
		if(isset($mytoken) && $mystatus == 'Available') {
			echo "    <item>
	      <title>",htmlspecialchars(stripslashes($mytoken),ENT_QUOTES,'UTF-8'),"</title>
	      <description>",(isset($mydetails)?htmlspecialchars(stripslashes($mydetails),ENT_QUOTES,'UTF-8'):""),"</description>
	      <link>".(isset($mylink)?'https://realt.co/marketplace/'.$mylink:"")."</link>
	      <guid>".(isset($mylink)?'https://realt.co/marketplace/'.$mylink:"")."</guid>
    </item>
";
		}
		unset($mytoken);
	}
	if(isset($mynext[1])) get_infos($mynext[1]);
}

get_infos(1);
 ?>
   </channel>
 </rss>
