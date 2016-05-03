<?php
include('/www/conf.php');

//function isvalidURL($url) {
//	return filter_var($url, FILTER_VALIDATE_URL);
//}

function validate_url($url) {
	$url = filter_var($url, FILTER_SANITIZE_URL);
	$test = filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED);
	// deal with bug //bugs.php.net/51192 (present in PHP 5.2.13 and PHP 5.3.2)
	if ($test === false) {
		$test = filter_var(strtr($url, '-', '_'), FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED);
	}
	if ($test !== false && $test !== null && preg_match('!^https?://!i', $url)) {
		return $url;
	} else {
		return false;
	}
}

$debug= 0;
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


//$_POST['link'] = "//accessoires-moto.shop.ebay.fr/14780/i.html?_ipg=25&_saact=1&_sop=12&LH_AvailTo=1&LH_PrefLoc=2&_nkw=(bop)&_dmpt=FR_JG_Moto_Pi%25C3%25A8ces&_rss=1";
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
$rsslink = validate_url($rsslink);
if(!$rsslink) {
	print "Ce lien n'est pas valide";
	exit;
}

if(!preg_match('/^https?:\/\//',$rsslink)) $rsslink = '//'.$rsslink;
$title = $link = $description = $language = '';
if($debug) echo "<b><u>$rsslink</b></u> : <br />";
$ch = curl_init();

curl_setopt_array($ch, Array(
CURLOPT_URL => $rsslink,
CURLOPT_USERAGENT => 'spider',
CURLOPT_TIMEOUT => 120,
CURLOPT_CONNECTTIMEOUT => 30,
CURLOPT_RETURNTRANSFER => TRUE,
CURLOPT_ENCODING => 'UTF-8',
CURLOPT_SSL_VERIFYPEER => FALSE,
CURLOPT_SSL_VERIFYHOST => FALSE
));
// curl_setopt($ch, CURLOPT_URL, $rsslink);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$page = trim(curl_exec($ch));
//print $rsslink."|".$page."|";
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
  	$link = clean_txt($link);
  	$description = clean_txt($description);

  	$v = $mysqli->query('select id from reader_flux where rss="'.$rsslink.'";') or die ($mysqli->error);
    if($v->num_rows == 0) {
      if($debug) print "pas trouvé !!!!<br />";
      $t = $mysqli->query('insert into reader_flux (title, description, language, rss, link) values ("'.$mysqli->real_escape_string(nl2br(trim($title))).'","'.$mysqli->real_escape_string(nl2br(trim($description))).'","'.$language.'","'.$rsslink.'","'.$link.'")') or die($mysqli->error);
      if($debug) print "Ajouté.<br />";
      if($mysqli->insert_id) {
	$id_flux = $mysqli->insert_id;
	$i = $mysqli->query('insert into reader_user_flux (id_user, id_flux) values ('.$_SESSION['user_id'].',"'.$mysqli->insert_id.'");') or die ($mysqli->error);
	//echo "Vous êtes maintenant inscrit à ce flux.";
      }
      else if($debug) echo "error pour récupérer l'id<br />";



      ////mise à jour du flux car nouveau.
      $title=null;
      $title = (isset($rss->title))?$rss->title:$rss->channel->title;
      if(!isset($title)) {
	if($debug) echo "pas de titre!";
	if($debug) echo '<pre>';
	if($debug) print_r($rss);
	if($debug) echo '</pre>';
	die("Pas de titre pour ce flux RSS!");
      }
      if($debug) echo "<h1>$title</h1>";

      if(isset($rss->channel->item)) $flux = $rss->channel->item;
      else if(isset($rss->item)) $flux=$rss->item;
      else if(isset($rss->entry)) $flux=$rss->entry;
      else {
	if($debug) echo "$j : <b>/!\ type de flux inconnu /!\</b><br />";
	if($debug) print_r($rss);
  die("Format du flux RSS non reconnu");
      }
      foreach ($flux as $item) {
	$link=null;
	if(is_object($item->link)) {
	  foreach($item->link as $t) {
	    if($t['rel'] == "alternate") $link = $t['href'];
	  }
	}
	if(!isset($link) && isset($item->link)) $link = $item->link;
	if(!isset($link)|| !$link || $link=='') {
	  if($debug) print $link." est vide.<br />";
	  if($debug) print_r($item->link);
	  die("Flux RSS vide.");
	}

	$guid = null;
	if(isset($item->guid)) $guid = $item->guid;
	if(isset($guid) && preg_match('/^\/\//',$guid)) $link = $guid;

	$v = $mysqli->query("select id from reader_item where id_flux=".$id_flux." and link='".$mysqli->real_escape_string($link)."';") or die($mysqli->error);
	if (!$v->num_rows) {
	  $title = null;
	  if($item->title) $title=$item->title;
	  else {
	    if($debug) echo "PAS DE TITRE !!!<br />";
	  }
	  $iDate = null;

	  if(isset($item->pubDate)) $iDate=$item->pubDate;
	  else if(isset($item->published)) $iDate = $item->published;
	  $date = new DateTime($iDate);
	  $iDate = $date->getTimestamp();
	  if(!isset($iDate) || $iDate > time()) $iDate = time();


	  $image=null;
	  if(isset($item->image)) $image=$item->image;
	  $content = null;
	  if(isset($item->description)) $content = $item->description;
	  else if(isset($item->content)) $content = $item->content;
	  else if(isset($item->summary)) $content = $item->summary;
	  else {
	    if($debug) print "Pas de content ?<br />";
	    if($debug) print_r($item);
	  }
	  if(!isset($content) || $content=='') {
	    if($debug) echo '<b>pas de content</b><br/>';
	    if($debug) print_r($item);
	    if($debug) echo '<br /><br />';
	  }

	  $author = null;

	  if(isset($item->author->name)) $author = $item->author->name;
	  else if(isset($item->author)) $author = $item->author;

	  if($debug) echo "&nbsp;&nbsp;id          : $id_flux<br />";
	  if($debug) echo "&nbsp;&nbsp;date        : $iDate<br />";
	  if($debug) echo "&nbsp;&nbsp;guid        : $guid<br />";
	  if($debug) echo "&nbsp;&nbsp;title       : $title<br />";
	  if($debug) echo "&nbsp;&nbsp;author      : $author<br />";
	  if($debug) echo "&nbsp;&nbsp;link        : $link<br />";
	  if($debug) echo "&nbsp;&nbsp;content     : $content<br /><br />";
	  $title = clean_txt($title);
	  $content = clean_txt($content);
	  $author = clean_txt($author);
  	$guid = clean_txt($guid);
  	//$language = clean_txt($language);
  	$link = clean_txt($link);
	  $mysqli->query("insert into reader_item values ('', $id_flux, '".date("Y-m-d H:i:s",$iDate)."', '".$mysqli->real_escape_string($guid)."', '".$mysqli->real_escape_string($title)."', '".$mysqli->real_escape_string($author)."', '".$mysqli->real_escape_string($link)."', '".$mysqli->real_escape_string($content)."');") or die($mysqli->error);
	}
	$mysqli->query("update reader_flux set `update`=NOW() where id=".$id_flux.";") or die($mysqli->error);
      }
    }
    else {
      $v = $mysqli->query('select id from reader_flux where rss="'.$rsslink.'";') or die ($mysqli->error);
      $id_flux = $v->fetch_row();
      $w = $mysqli->query("select id from reader_user_flux where id_flux=".$id_flux[0].";") or die($mysqli->error);
      if($w->num_rows == 0) {
//		echo "insert into reader_user_flux (id_user,id_flux) values (".$_SESSION['user_id'].", ".$id_flux[0].");";
	$mysqli->query("insert into reader_user_flux (id_user,id_flux) values (".$_SESSION['user_id'].", ".$id_flux[0].");") or die($mysqli->error);
	//echo "Vous êtes maintenant inscrit au flux <b>\"".$title."\"</b><br />";
      }
      else
	echo "Vous êtes déjà inscrit à ce flux.";
    }
  }
  else {
    if($debug) echo 'données manquantes pour insérer<br />';
    if($debug) echo "<br /><br /><pre>";
    if($debug) print_r($rss);
    if($debug) echo "</pre>";
  }
  if($debug) echo "<br />";
}
else {
  // var_dump($page);
  echo 'Ce flux n\'est pas actif pour le moment.';
}
