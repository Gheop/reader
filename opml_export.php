<?php
include('/www/conf.php');
if (file_exists('opml.xml')) $opml = simplexml_load_file('opml.xml');
else exit('Failed to open opml.xml.');
  $flag = 0;

foreach($opml->body->outline as $t) {
  $title = $link = $description = $language = ''; 
  $rsslink = $t['xmlUrl'][0];
  $link = $t['htmlUrl'][0];
  echo "<b><u>$rsslink</b></u> : <br />";
  if($rss = @simplexml_load_file($rsslink)) {
    
    //title
    if($rss->channel->title) {
      $title = $rss->channel->title;
    }
    elseif($rss->title) {
      $title = $rss->title;
    }
    
    //link
    if($rss->channel->link) {
      $link = $rss->channel->link;
	}
    elseif($rss->link[0]['href']) {
      $link = $rss->link[0]['href'];
    }
    
    //description/subtitle
    if($rss->channel->description)
      $description = $rss->channel->description;
    elseif($rss->subtitle)
      $description = $rss->subtitle;
    
    //language
    if($rss->channel->language)
      $language = $rss->channel->language;
    elseif($rss->language) 
      $language = $rss->language;
    
    
    if(isset($title) && $title) { echo "$title|$description|$language|$rsslink|$link<br />";}
    
    if($title && $rsslink && $link) {
      $v = $mysqli->query('select id from reader_flux where rss="'.$rsslink.'";') or die ($mysqli->error);
      if($v->num_rows == 0) {
	print "pas trouvé !!!!<br />";
        $t = $mysqli->query('insert into reader_flux (title, description, language, rss, link) values ("'.$mysqli->real_escape_string(trim($title)).'","'.$mysqli->real_escape_string(trim($description)).'","'.$language.'","'.$rsslink.'","'.$link.'")') or die($mysqli->error);
	print "Ajouté.<br />";
	if($mysqli->insert_id) $i = $mysqli->query('insert into reader_user_flux (id_user, id_flux) values (1,"'.$mysqli->insert_id.'");') or die ($mysqli->error);
	else echo "error pour récupérer l'id<br />";
	
	
      }
      else { 
	echo "ce flux existe déjà<br />";
      }
    }
    else {
      echo 'données manquantes pour insérer<br />';
      echo "<br /><br /><pre>";
      print_r($rss);
      echo "</pre>";
    }
    echo "<br />";
  }
  else
    {
      var_dump($rss);
      echo 'Ne peut ouvrir ce flux.<br />';
    }
}

