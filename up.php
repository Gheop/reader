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

function get_flux($r) {

}

$r = get_links($mysqli, $extra);
$mh = curl_multi_init();
$ch = array();
$dd = array();
$query = '';
$i = 0;
while($d = $r->fetch_array()) {
	$ch[$i] = curl_init();
	curl_setopt_array($ch[$i],
		Array(
			CURLOPT_URL => $d[1],
			CURLOPT_USERAGENT => 'GheopReader',
			CURLOPT_TIMEOUT => 5,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_ENCODING => 'UTF-8',
            CURLOPT_SSL_VERIFYPEER => FALSE,
            CURLOPT_SSL_VERIFYHOST => FALSE
			)
		);
	curl_multi_add_handle($mh, $ch[$i]);
	$dd[$i] = $d[0];
	$i++;
}

$running=null;

do {
	curl_multi_exec($mh,$running);
	//usleep (1000);
} while ($running > 0);

$test = '';
for($j=0;$j<$i;$j++) {
	//echo "<i>$dd[$j]</i><br />";
	$tt = $mysqli->query("select link, title, rss from reader_flux where id=".$dd[$j].";") or die($mysqli->error);
	$ttt = $tt->fetch_array();
	print "<h2><a href=\"$ttt[0]\">$ttt[1]</a> (<a href=\"$ttt[2]\">rss</a>)</h2>";
	if($DEBUG) libxml_use_internal_errors(true);

	$rss = @simplexml_load_string(trim(curl_multi_getcontent($ch[$j])), 'SimpleXMLElement', LIBXML_NOCDATA);
//echo "OK!<br />";
	if (!$rss and $DEBUG) {
		foreach (libxml_get_errors() as $error) {
			print $error;
        // gérer les erreurs ici
		}
		echo '<pre>';
		print curl_multi_getcontent($ch[$j]);
		echo '</pre>';
		libxml_clear_errors();
	}
 //if($DEBUG) $rss = simplexml_load_string(trim($test), 'SimpleXMLElement', LIBXML_NOCDATA);
	if($DEBUG) echo '<pre>';
	if($DEBUG) print_r($rss);
	if($DEBUG) echo '</pre>';
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
		if(is_object($item->link)) {
			foreach($item->link as $t) {

				if($t['rel'] == "alternate" ||$t['rel'] == "self") $link = $t['href'];
				if(!isset($link) && isset($t['href'])) $link = $t['href'];
			}
		}
		if(!isset($link) && isset($item->link) && preg_match('/^https?:\/\//',$item->link)) $link = $item->link;
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

		$v = $mysqli->query("select id from reader_item where id_flux=".$dd[$j]." and link='".$mysqli->real_escape_string($link)."';") or die($mysqli->error);
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
			if(isset($item->description)) $content = $item->description;
			else if(isset($item->content)) $content = $item->content;
			else if(isset($item->summary)) $content = $item->summary;
			else {
				print "pas de content<br />";
				if(preg_match('/^(\/\/)?(www.)?youtube.com\/watch\?v=(.*)/', $link, $m)) {
					echo "Lien youtube trouvé!<br />";
      		//$content = '<yt width="560" height="315" src="https://www.youtube.com/embed/'.$m[3].'" frameborder="0" allowfullscreen></yt>';
					$content = '<span class="youtube-embed">'.$m[3].'</span>';
				}
				else if(preg_match('/^(\/\/)?(www.)?youtu.be\/(.*)\??.*/', $link, $m)) {
					echo "Lien youtube trouvé!<br />";
      		//$content = '<yt width="560" height="315" src="https://www.youtube.com/embed/'.$m[3].'" frameborder="0" allowfullscreen></yt>';
					$content = '<span class="youtube-embed">'.$m[3].'</span>';
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

			print "CONTENT : |$content|";
			$author = null;

			if(isset($item->author->name)) $author = $item->author->name;
			else if(isset($item->author)) $author = $item->author;
			else $author ='Unknow';
			/* echo "&nbsp;&nbsp;id          : $dd[$j]<br />"; */
			/* echo "&nbsp;&nbsp;date        : $iDate<br />"; */
			/* echo "&nbsp;&nbsp;guid        : $guid<br />"; */
			/* echo "&nbsp;&nbsp;title       : $title<br />"; */
			/* echo "&nbsp;&nbsp;author      : $author<br />"; */
			/* echo "&nbsp;&nbsp;link        : $link<br />"; */
			/* echo "&nbsp;&nbsp;content     : $content<br /><br />"; */
			$title = clean_txt($title);
			$content = clean_txt($content);
			$author = clean_txt($author);
			print "MAJ<br />";
			$mysqli->query("insert into reader_item values ('', $dd[$j], '".date("Y-m-d H:i:s",$iDate)."', '".$mysqli->real_escape_string($guid)."', '".$mysqli->real_escape_string($title)."', '".$mysqli->real_escape_string($author)."', '".$mysqli->real_escape_string($link)."', '".$mysqli->real_escape_string($content)."');") or die($mysqli->error);
		}
		$mysqli->query("update reader_flux set `update`=CURRENT_TIMESTAMP() where id=".$dd[$j].";") or die($mysqli->error);
		print ".";
	}
	curl_multi_remove_handle($mh,$ch[$j]);
}
curl_multi_close($mh);
?>