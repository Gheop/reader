<?php
error_reporting(-1);
include('/www/conf.php');
include('scraping/simple_html_dom.php');

$debug = false;




function getContentUrl($url) {
	$ch = curl_init();
	curl_setopt_array($ch, Array(
		CURLOPT_URL => $url,
		CURLOPT_USERAGENT => 'GheopSpider',
		CURLOPT_TIMEOUT => 120,
		CURLOPT_CONNECTTIMEOUT => 30,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_ENCODING => 'UTF-8',
		CURLOPT_SSL_VERIFYPEER => FALSE,
		CURLOPT_SSL_VERIFYHOST => FALSE
		)
	);
	return trim(curl_exec($ch));
}

function isRSSContent($content) {
	try {
		$rss = @new SimpleXmlElement($content);
	}
	catch(Exception $e){
		return false;
	}
	if(
		(isset($rss->channel->item) and $rss->channel->item->count() > 0)
		or (isset($rss->item) and $rss->item->count() > 0)
		or (isset($rss->entry) and $rss->entry->count() > 0)
		)
		return true;
	else {
		return false;
	}
}

function searchRSSUrlSpecialSite($url) {

/* 
 * youtube
 */
	if(preg_match('/^.*\/\/www\.youtube\.com\/channel\/(.*)$/',$url, $m )) {
		return 'https://www.youtube.com/feeds/videos.xml?channel_id='.$m[1];
	}
	else if(preg_match('/^.*\/\/www\.youtube\.com\/user\/([^\?\&\/]*)(.*)$/',$url, $m )) { 
		$html = file_get_html($url);

		foreach($html->find('meta[itemprop=channelId]') as $element) {
			$url = $element->content;
			return 'https://www.youtube.com/feeds/videos.xml?channel_id='.$url;
		}
		return false;
	}	
	else if(preg_match('/^.*\/\/(www\.)?(youtube\.com|youtu.be|youtube-nocookie\.com)\/(watch\?.*\&?v=|embed\/|)([^\?\&]*)(.*)$/',$url, $m )) {
		$html = file_get_html('https://www.youtube.com/watch?v='.$m[4]);
		foreach($html->find('meta[itemprop=channelId]') as $element) {
			$url = $element->content;
			return 'https://www.youtube.com/feeds/videos.xml?channel_id='.$url;
		}
		return false;
	}
/*
 * dailymotion 
 */
	else if(preg_match('/^.*dai\.ly\/(.*)$/', $url, $m)) {
		$url = 'http://www.dailymotion.com/video/'.$m[1];
		return searchRSSUrlSpecialSite($url);
	}
	else if(preg_match('/^.*\/\/(www\.)?dailymotion\.com\/(embed\/)?video\/([^\?]*)(.*)?$/',$url, $m )) {
		if($html = file_get_html('http://www.dailymotion.com/video/'.$m[3])) {
		foreach($html->find('meta[property=video:director]') as $element) {
			$url = $element->content;
			return searchRSSUrlSpecialSite($url);
		}
	}
		return false;
	}
	else if(preg_match('/^.*\/\/www\.dailymotion\.com\/([^\/\?].*)$/',$url, $m )) {
		return 'http://www.dailymotion.com/rss/user/'.$m[1];
	}
	return false;
}


### function récupérée bien dégueulasse, à nettoyer
function searchRSSUrl($url) {

	//à passer à la fin qd la suite sera finie.
	if($url = searchRSSUrlSpecialSite($url)) return $url;
	
	$doc = new DOMDocument();
	$doc->strictErrorChecking = FALSE;
	libxml_use_internal_errors(true);
	@$doc->loadHTML(getContentUrl($url));
	libxml_clear_errors();

	$xml = simplexml_import_dom($doc);
	$xpath_results = $xml->xpath('//link[@rel="alternate"]');
	foreach ( $xpath_results as $node ) {
		if($node['type'] == 'application/rss+xml' or $node['type'] == 'application/rss+xml') {
			echo (string)$node['title']."->".(string)$node['href']."<br />";
			$ret[] = array($node['title'],$node['href']); 
		}
	}
	die;
	if(!empty($ret)) {
		return $ret;
	}
	$s = parse_url($url);
	print_r($s);
	die;
	if(isset($s[path])){
		print "$s[path]<br />";
		die;
	}
	else return false;
}

function getRSSLink($url) {
	$content = getContentUrl($url); 
	if(!isset($content)) {
		echo "<br />La page $url n'a pas de contenu.<br />";
		return false;
	}
	elseif (isRSSContent($content)) {
		return $url;
		# code...
	}
	elseif($url = searchRSSUrl($url)) {
		//echo $url;
		if(isRSSContent(getContentUrl($url)))
			return $url;
		else return false;
	}
	else {
		return false;
	}
	/*try {
		if(isRSSContent($content)) return $url;
	}
	catch(Exception $e){
		return ($url = searchRSSUrl($url))?$url:false;
	}*/
	if(isset($rss->channel->item) and $rss->channel->item->count() > 0)
		return $url;
	else
		return false;
}

function validate_url($url) {
	if(!preg_match('/^https?:\/\//',$url)) $url = '//'.$url;
	$url = filter_var($url, FILTER_SANITIZE_URL);
	if (!filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED) === false) {
		return $url;
	} else {
		return false;
	}
}

include('clean_text.php');

if(!isset($_SESSION['pseudo'])) {
	if (isset($_COOKIE['session'])) {
		$auth = explode("|",$_COOKIE['session']);
		$req = $mysqli->query("select * from users where pseudo='$auth[0]'");
		$d = $req->fetch_array();
		$pwd=$d['pwd'];
		if($d['pwd'] == $auth[1])
		{
			$_SESSION['pseudo'] = $d['pseudo'];
			$_SESSION['user_id'] = $d['id'];
			setrawcookie("session", "$d[pseudo]|$d[pwd]", time()+26000000, '/', '.gheop.com');
		}
	}
}
if(!isset($_SESSION['user_id'])) {
	echo "Vous n'êtes pas authentifié sur Gheop!";
	exit;
}


if(isset($_POST['link'])) {
	$rsslink = $_POST['link'];
}
else if(isset($_GET['f'])) {
	$rsslink = $_GET['f'];
}
else {
	print "Pas de lien trouvé";
	exit;
}

if(preg_match('/^[@#](.*)$/',$rsslink,$m)) {
 //echo "$m[1]"; exit;
 $rsslink = 'https://reader.gheop.com/scraping/twitter.com.php?f='.$m[1];
 
}

if(!$rsslink = validate_url($rsslink)) {
	print "Ce lien n'est pas valide.";
	exit;
}

$title = $link = $description = $language = '';


if(!$rsslink = getRSSLink($rsslink)) {
	echo 'Ce site n\'a pas de flux rss';
	exit;
}

$page = getContentUrl($rsslink);
if($rss = @simplexml_load_string($page)) {
	if($rss->channel->title) $title = $rss->channel->title;
	elseif($rss->title) $title = $rss->title;

	if($rss->channel->link) $link = $rss->channel->link;
	elseif($rss->link[0]['href']) $link = $rss->link[0]['href'];

	if($rss->channel->description) $description = $rss->channel->description;
	elseif($rss->subtitle) $description = $rss->subtitle;

	if($rss->channel->language) $language = $rss->channel->language;
	elseif($rss->language) $language = $rss->language;

	if(isset($title) && $title) { if($debug) echo "$title|$description|$language|$rsslink|$link<br />";}

	if($title && $rsslink && $link) {
		$title = clean_txt($title);
		$rsslink = clean_txt($rsslink);
		$language = clean_txt($language);
/*		print $link;*/
		$link = clean_txt($link);
		$description = clean_txt($description);

		$v = $mysqli->query('select id from reader_flux where rss="'.$rsslink.'";') or die ($mysqli->error);
    #Le flux n'est pas déjà présent dans la base, on l'ajoute
		/*$id_flux*/
		if($v->num_rows == 0) {
			$t = $mysqli->query('insert into reader_flux (title, description, language, rss, link) values ("'.$mysqli->real_escape_string(nl2br(trim($title))).'","'.$mysqli->real_escape_string(nl2br(trim($description))).'","'.$language.'","'.$rsslink.'","'.$link.'")') or die($mysqli->error);
			if($mysqli->insert_id) {
				$id_flux = $mysqli->insert_id;
				$i = $mysqli->query('insert into reader_user_flux (id_user, id_flux) values ('.$_SESSION['user_id'].',"'.$mysqli->insert_id.'");') or die ($mysqli->error);
			}
			else if($debug) echo "error pour récupérer l'id<br />";

			$upFlux = getContentUrl('https://reader.gheop.com/up.php?id='.$id_flux);
			if(isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != 'https://reader.gheop.com/') {
				header( "refresh:5;url=$link" );
				echo 'Vous êtes maintenant inscrit à ce flux.<br />Vous allez être rediriger sur le site d\'origine dans 5 secondes.<br /> Sinon, cliquer <a href="'.$link.'">ici</a>.';
			} else {
				echo "Vous êtes maintenant inscrit à ce flux.";
			}
			exit;
		}
		else {
			$v = $mysqli->query('select id from reader_flux where rss="'.$rsslink.'";') or die ($mysqli->error);
			$id_flux = $v->fetch_row();
			$w = $mysqli->query("select id from reader_user_flux where id_flux=".$id_flux[0].";") or die($mysqli->error);
			if($w->num_rows == 0) {
				$mysqli->query("insert into reader_user_flux (id_user,id_flux) values (".$_SESSION['user_id'].", ".$id_flux[0].");") or die($mysqli->error);
			$upFlux = getContentUrl('https://reader.gheop.com/up.php?id='.$id_flux);
				if(isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != 'https://reader.gheop.com/') {
					header( "refresh:5;url=$link" );
					echo 'Vous êtes maintenant inscrit à ce flux.<br />Vous allez être rediriger sur le site d\'origine dans 5 secondes.<br /> Sinon, cliquer <a href="'.$link.'">ici</a>.';
				} else {
					echo 'Vous êtes maintenant inscrit à ce flux.';
					$previous = "javascript:history.go(-1)";
					echo '<a href="'.$previous.'">Back</a>';
				}
				exit;
			}
			else
			{
				if(isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != 'https://reader.gheop.com/') {
					header( "refresh:5;url=$link" );
					echo 'Vous êtes déjà inscrit à ce flux.<br />Vous allez être rediriger sur le site d\'origine dans 5 secondes.<br /> Sinon, cliquer <a href="'.$link.'">ici</a>.';
				} else {
					echo 'Vous êtes déjà inscrit à ce flux.';
					$previous = "javascript:history.go(-1)";
					echo '<a href="'.$previous.'">Back</a>';
				} 
				exit;
			}
		}
	}
	else {
		if($debug) echo 'données manquantes pour insérer<br />';
		if($debug) echo "<br /><br /><pre>";
		if($debug) print_r($rss);
		if($debug) echo "</pre>";
	}
	if($debug) echo "<br />";
	echo "OK";
}
else {
  // var_dump($page);
	echo 'Ce flux n\'est pas actif pour le moment.';
}
