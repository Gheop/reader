<?php
include('/www/conf.php');
include('clean_text.php');

	$extra ='';
	if(isset($_GET['id']) && is_numeric($_GET['id'])) $extra = ' where id='.$_GET['id'];
	$DEBUG = 0;
	if(isset($_GET['debug'])) {
		$DEBUG = 1;
		if(isset($_GET['id']) && is_numeric($_GET['id'])) {
			$mysqli->query("delete from reader_item where id_flux=$_GET[id];") or die($mysqli->error);
			$mysqli->query("delete FROM reader_user_item where id_item not in (select id from reader_item);") or die($mysqli->error);
		}
	}


function get_links() {
	global $mysqli, $extra;
	$r = $mysqli->query('select id, rss from reader_flux'.$extra.' order by id;') or die($mysqli->error);
	return $r;
}

function get_title() {

}

function complete_link($link, $linkmaster) {
	if(isset($link) && !preg_match('/^https?:\/\//',$link)) {
		if(substr($link,0,1) == '/')  {
			$pu = parse_url($linkmaster);
			if(!isset($pu['scheme'])) $pu['scheme'] = 'https';
			if(substr($link,1,1) == '/') {
				$link = $pu['scheme'].':'.$link;
			} else {
				$link = $pu['scheme'].'://'.$pu['host'].$link;
			}
		} else {
			$link = $linkmaster.'/'.$link;
		}
	}
return $link;
}

function get_flux($r) {

}

$r = get_links($mysqli, $extra);
$mh = curl_multi_init();
$ch = array();
$dd = array();
$urlorigin = array();
$query = '';
$i = 0;
while($d = $r->fetch_array()) {
	$ch[$i] = curl_init();
	curl_setopt_array($ch[$i],
		Array(
			CURLOPT_URL => $d[1],
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 5.1; rv:31.0) Gecko/20100101 Firefox/31.0',
			CURLOPT_TIMEOUT => 60,
			CURLOPT_CONNECTTIMEOUT => 60,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_ENCODING => 'UTF-8',
            CURLOPT_SSL_VERIFYPEER => FALSE,
            CURLOPT_SSL_VERIFYHOST => FALSE,
            CURLOPT_FOLLOWLOCATION => TRUE,
            CURLOPT_MAXREDIRS => 3
			)
		);
	curl_multi_add_handle($mh, $ch[$i]);
	$dd[$i] = $d[0];
	$urlorigin[$i] = $d[1];
	$i++;
}

$running=null;

do {
	curl_multi_exec($mh,$running);
	//usleep (1000);
} while ($running > 0);

//$test = 'https://news.google.fr/news?cf=all&hl=fr&pz=1&ned=fr&topic=h&num=3&output=rss';
for($j=0;$j<$i;$j++) {
	//echo "<i>$dd[$j]</i><br />";
	$tt = $mysqli->query("select link, title, rss from reader_flux where id=".$dd[$j].";") or die($mysqli->error);
	$ttt = $tt->fetch_array();
	print "<h2><a href=\"$ttt[0]\">$ttt[1]</a> (<a href=\"$ttt[2]\">rss</a>)</h2>";
	if($DEBUG) libxml_use_internal_errors(true);
	//if($DEBUG) var_dump(curl_multi_getcontent($ch[$j]));

	$xml = trim(curl_multi_getcontent($ch[$j]));
	$xml = preg_replace('/^(.*<\/rss>).*$/s', '\\1', $xml);	
// $xml = tidy_repair_string($xml, array(
	//     'output-xml' => true,
	//     'input-xml' => true
	// ));
$rss = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
//$rss = simplexml_load_string($xml, null, LIBXML_NOCDATA);
	//$rss = simplexml_load_file($url, null, LIBXML_NOCDATA);
//$namespaces = $rss->getNamespaces(true);
// $media_content = $rss->entry->item[0]->children($namespaces['media']);
// foreach($media_content->group->content as $i){
//     var_dump((string)$i->attributes()->url);
// }
// die;
	//$rss = $xml->asXML();
	// echo '<pre>';
	// print_r($rss);
	// die;
// 	$rss = new DomDocument();
// 	$rss->recover=true;
// 	$rss->loadXML(trim(curl_multi_getcontent($ch[$j])));
// $rss = $rss->saveXML();

	$redirectURL = curl_getinfo($ch[$j],CURLINFO_EFFECTIVE_URL );
/*	echo '<h1>'.$urlorigin[$j].'</h1>';
	echo '<h1>'.$redirectURL.'</h1>';*/
	if($urlorigin[$j] != $redirectURL) {
		$mysqli->query("update reader_flux set rss='$redirectURL' where id=$dd[$j];") or die($mysqli->error);
	}
//echo "OK!<br />";
	if (!$rss and $DEBUG) {
		foreach (libxml_get_errors() as $error) {
			var_dump( $error );
        // gérer les erreurs ici
		}
		echo '<pre>';
		print curl_multi_getcontent($ch[$j]);
		echo '</pre>';
		libxml_clear_errors();
	}
 //if($DEBUG) $rss = simplexml_load_string(trim($test), 'SimpleXMLElement', LIBXML_NOCDATA);
/*	if($DEBUG) echo '<pre>';
	if($DEBUG) print_r($rss);
	if($DEBUG) echo '</pre>';*/
	if(empty($rss)) {
		echo 'Flux vide!<br />';

		continue;
	}
	$title=null;
	$title = (isset($rss->title))?$rss->title:$rss->channel->title;
	if(!isset($title)) {
		echo "pas de titre!";
		if($DEBUG) echo '<pre>';
		if($DEBUG) print_r($rss);
		if($DEBUG) echo '</pre>';
		continue;
	}
	echo "<h1>$title</h1>";
	$linkmaster = null;
	if($rss->channel->link) $linkmaster = $rss->channel->link;
	elseif($rss->link[0]['href']) $linkmaster = $rss->link[0]['href'];

// 	if(is_object($rss->children($namespaces['media']))) {$flux=$rss->children($namespaces['media']);
// // echo '<pre>';print_r($flux);
// }
// 	else 
		if(isset($rss->channel->item)) $flux = $rss->channel->item;
	else if(isset($rss->item)) $flux=$rss->item;
	else if(isset($rss->entry)) $flux=$rss->entry;
	else {
		echo "$j : <b>/!\ type de flux inconnu /!\</b><br />";
		print_r($rss);
		continue;
	}
	foreach ($flux as $item) {
		$link=null;
		// echo '<pre>';
		// print_r($item);
		if(is_object($item->link)) {
			foreach($item->link as $t) {

				if($t['rel'] == "alternate" ||$t['rel'] == "self") $link = $t['href'];
				if(!isset($link) && isset($t['href'])) $link = $t['href'];
			}
		}
		if(!isset($link) && isset($item->link) && preg_match('/^https?:\/\//',$item->link)) $link = $item->link;
		//le lien n'est pas complet !
		if(!preg_match('/^https?:\/\//',$linkmaster)) $linkmaster = $ttt[0];
		//echo "<h1>$linkmaster</h1><br />";
		$link = complete_link($link, $linkmaster);

		// print $link;
		// die;
		if(!isset($link)|| !$link || $link=='') {
			print "Aucun lien trouvé.<br />";
			if($DEBUG) echo '<pre>';
			if($DEBUG) print_r($rss);
			if($DEBUG) echo '</pre>';
			continue;
		}
		$guid = null;
		if(isset($item->guid)) $guid = $item->guid;
		if(isset($guid) && preg_match('/^https?:\/\/.*/',$guid)) $link = $guid;
		if($DEBUG) echo 'Lien : <a href="'.$link.'">'.$link.'</a><br />';



//nettoyage rapide de link (à compléter surement)
		$a = array(')','(','"','\\');
		$b = array('','','','\\\\');
		$link = str_replace($a, $b, $link);
		$link_without_security = preg_replace('/^https?/','' , $link);
		$v = $mysqli->query("select id from reader_item where id_flux=".$dd[$j]." and link like '%".$mysqli->real_escape_string($link_without_security)."';") or die($mysqli->error);
		if (!$v->num_rows) {
			$title = null;
			if($item->title) $title=$item->title;
			else {
				echo "PAS DE TITRE !!!<br />";
			}
			$iDate = null;

			if(isset($item->pubDate)) $iDate=$item->pubDate;
			else if(isset($item->published)) $iDate = $item->published;
			echo "|$iDate|<br>";
			try {
				$date = new DateTime($iDate);
			} catch (Exception $e) {
				echo $e->getMessage();
				$date = new DateTime();
			}
			$iDate = $date->getTimestamp();
			if(!isset($iDate) || $iDate > time()) $iDate = time();


			$image=null;
			if(isset($item->image)) $image=$item->image;
			$content = null;
			/*if(isset($item->enclosure)) {
				echo '<pre><h1>';
	print_r($item->enclosure);
	echo $item->enclosure['url'];
	echo '</h1></pre>';
//				die ("FUCK OFF!");

			}*/

			// if($item->children($namespaces['media'])) $content=$item->children($namespaces['media']);
			// else 
				if(isset($item->description)) $content = $item->description;
			//else if(isset($item->{'media:description'})) $content = $item->{'media:description'}; //ne marche pas
			//voir xpath dans TESTS
			else if(isset($item->content)) $content = $item->content;
			else if(isset($item->summary)) $content = $item->summary;
			else if(isset($item->media)) var_dump($item->media);
			else {
				print "pas de content<br />";
				if(preg_match('/^(.*\/\/)?(www.)?youtube.com\/watch\?v=(.*)/', $link, $m)) {
					echo "Lien youtube trouvé!<br />";
      		//$content = '<yt width="560" height="315" src="https://www.youtube.com/embed/'.$m[3].'" frameborder="0" allowfullscreen></yt>';
					$content = '<yt>'.$m[3].'</yt>';
				}
				else if(preg_match('/^(\/\/.*\.(jpe?g|gif|png))/', $link, $m)) {
					echo "Image trouvée!<br />";
					$content = '<img src="'.$m[1].'" />';
				}
			}
			if(!isset($content) || $content=='') {
				echo '<b>pas de content</b><br/>';
				print_r($item);
				echo '<br /><br />';
			}

			//print "CONTENT : |$content|";
			$author = null;

			if(isset($item->author->name)) $author = $item->author->name;
			else if(isset($item->author)) $author = $item->author;
			else $author ='';
			/* echo "&nbsp;&nbsp;id          : $dd[$j]<br />"; */
			/* echo "&nbsp;&nbsp;date        : $iDate<br />"; */
			/* echo "&nbsp;&nbsp;guid        : $guid<br />"; */
			/* echo "&nbsp;&nbsp;title       : $title<br />"; */
			/* echo "&nbsp;&nbsp;author      : $author<br />"; */
			/* echo "&nbsp;&nbsp;link        : $link<br />"; */
			 //echo "&nbsp;&nbsp;content     : $content<br /><br />";

			$title = clean_txt($title);
			$content = clean_txt($content);
			//echo "&nbsp;&nbsp;content     : $content<br /><br />";
			$author = clean_txt($author);
			print "MAJ<br />";
/*			echo "insert into reader_item values ('', $dd[$j], '".date("Y-m-d H:i:s",$iDate)."', '".$mysqli->real_escape_string($guid)."', '".$mysqli->real_escape_string($title)."', '".$mysqli->real_escape_string($author)."', '".$mysqli->real_escape_string($link)."', '".$mysqli->real_escape_string($content);
			echo "<br /><br />";*/
			$mysqli->query("insert into reader_item values ('', $dd[$j], '".date("Y-m-d H:i:s",$iDate)."', '".$mysqli->real_escape_string($guid)."', '".$mysqli->real_escape_string($title)."', '".$mysqli->real_escape_string($author)."', '".$mysqli->real_escape_string($link)."', '".$mysqli->real_escape_string($content)."');") or die($mysqli->error);
		}
		$mysqli->query("update reader_flux set `update`=CURRENT_TIMESTAMP() where id=".$dd[$j].";") or die($mysqli->error);
		print ".";
	}
	curl_multi_remove_handle($mh,$ch[$j]);
}
curl_multi_close($mh);
?>
