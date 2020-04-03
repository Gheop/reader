<?php
require('simple_html_dom.php');
include('../clean_text.php');
$base_url = 'https://blog.lightning.engineering';
$site_name = 'Lightning Labs Blog';

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

echo "    <title>$site_name</title>
    <description>$site_name</description>
    <link>$base_url</link>
";

$html = file_get_html($base_url);

foreach($html->find('article[class="post"] header[class="post-header"] h2[class="post-title"] a') as $articles) {
  $article_link = $base_url.$articles->href;
  //if($article_link[0] == '/') $article_link = $base_url.$article_link;
  $detail = file_get_html($article_link);

  foreach($detail->find('h1[class="post-title"]') as $t) {
    $article_title = $t->plaintext;
    break;
  }
  foreach($detail->find('section[class="post-meta"] time[class="post-date"]') as $d) {
    $article_date = date('r',strtotime($d->datetime));
    break;
  }
  foreach($detail->find('section[class="post-content"]') as $c) {
    $article_content = $c->innertext;
    $article_content = preg_replace('/src=([\'\"])\//', "src=$1$base_url/", $article_content);
  }

	echo "    <item>
	      <title>",htmlspecialchars(stripslashes($article_title),ENT_QUOTES,'UTF-8'),"</title>
	      <pubDate>$article_date</pubDate>
        <description>",(isset($article_content)?htmlspecialchars(stripslashes($article_content),ENT_QUOTES,'UTF-8'):""),"</description>
	      <link>".(isset($article_link)?$article_link:"")."</link>
	      <guid>".(isset($article_link)?$article_link:"")."</guid>
    </item>
";
  $detail->clear();
  unset($detail);
}
?>
  </channel>
</rss>
