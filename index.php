<?php
//header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
//header("Content-Security-Policy: img-src: 'self'");
//header('Content-Type: text/html; charset=utf-8');
//header('X-Frame-Options: deny');
//header('X-Content-Type-Options: nosniff');
include('/www/conf.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>

<link rel="stylesheet" type="text/css" href="//reader.gheop.com/screen.css" media="screen" title="Normal" />
<title>Gheop Reader</title>
<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
<link id="favico" href="//reader.gheop.com/favicon.gif" rel="shortcut icon" type="image/gif" />
<!-- <link id="favico" href="//reader.gheop.com/favicon.svg" rel="shortcut icon" type="image/svg+xml" /> -->
<link rel="apple-touch-icon" href="favicon.gif"/>  
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black" />
</head>
<body>
<?php 
  if(!isset($_SESSION['pseudo'])) {
    if (isset($_COOKIE['session'])) {
      $auth = explode("|",$_COOKIE['session']);
      $req = $mysqli->query("select id, pseudo, pwd from users where pseudo='$auth[0]'");
      $d = $req->fetch_array();
      if($d['pwd'] == $auth[1]) {
	     $_SESSION['pseudo'] = $d['pseudo'];
	     $_SESSION['user_id'] = $d['id'];
	     setrawcookie("session", "$d[pseudo]|$d[pwd]", time()+26000000, '/', '.gheop.com');
	    }
    }
  } 
   else if(isset($_GET['a']) && $_GET['a'] == 'destroy') include('../destroy.php');
?>
<div id="register">
<?php
if(!isset($_SESSION['pseudo'])) {
  echo '<a href="//gheop.com/register?page=reader.gheop.com">[S\'enregister]</a> - <a href="//gheop.com/register/ident.php?page=reader.gheop.com">[S\'identifier]</a>';}
else {
  echo $_SESSION['pseudo'],' <a href="?a=destroy" title="Se déconnecter"></a>';}
?>
</div>
    <h1 id="g" class="animated swing"><a href="//reader.gheop.com/"><span id="g1">Gheop </span><span id="g2">Reader</span></a></h1>
<?php
if(isset($_SESSION['user_id'])) {
echo '
<div id="error" style="display:none;"></div>'; ?>

<div id="sdiv">
   <form onsubmit="search($('s').value);return false;" >
   <input id="s" type="text" name="s" />
   <button id="bs" onclick="search($('s').value);return false;"></button>
   </form>
</div>
<?php
echo '<ul id="menu">
  <li id="fall" class="fluxnew" onclick="view(\'all\')" title="Tout voir">All&nbsp;&nbsp;&nbsp;
    <a title="Ajouter un flux" onclick="addflux();"></a>&nbsp;&nbsp;&nbsp;
    <a title="Importer des flux"></a>&nbsp;&nbsp;&nbsp;
    <a title="Exporter mes flux"></a>&nbsp;&nbsp;&nbsp;
    <a onclick="navigator.registerContentHandler(\'application/atom+xml\', \'//reader.gheop.com/add_flux.php?f=%s\',  \'Gheop Reader\');return false;" title="Enregistrer comme lecteur de flux du navigateur"></a>&nbsp;&nbsp;&nbsp;
    <a href="manage.php" title="Réglages"></a>&nbsp;&nbsp;&nbsp;
    <a onclick="up();" title="Mettre à jour les flux"></a>
  </li>
</ul>
<div id="page">
</div>';
//<script type="text/javascript" nonce="EDNnf03nceIOfn39fn3e9h3sdfa">i();</script>';
} 
else {
  echo '<h2>Suivez l\'actualité de tous vos sites et blogs préférés.</h2><fieldset><legend>Simple</legend><br />Gheop Reader récupère en permance les nouveautés de tous vos sites favoris grâce à leur flux RSS et Atom.<br />Totalement gratuit et libre, un simple navigateur vous permet de suivre toute votre actualité de partout sans rien installer.<br /><br /></fieldset><br /><br /><fieldset><legend>Comment faire ?</legend><br />Il suffit de vous inscrire ou de vous identifier sur Gheop, d\'ajouter ou d\'importer vos fluxs et c\'est parti !<br /><br /></fieldset><br /><br /><fieldset><legend>Et ma vie privée dans tout ça ?</legend><br />Vous pouvez quitter Gheop Reader dès que vous le souhaitez, récupérer vos données sans rien perdre, voir même héberger votre Gheop Reader chez vous pour être totalement indépendant.<br /><br /></fieldset>';
}
?>
  </body>
</html>
<script src="//reader.gheop.com/lib.js" type="text/javascript"></script>
<!-- Piwik -->
<!--<script type="text/javascript">
  var _paq = _paq || [];
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u="//gheop.com/stats/";
    _paq.push(['setTrackerUrl', u+'piwik.php']);
    _paq.push(['setSiteId', 2]);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'piwik.js'; s.parentNode.insertBefore(g,s);
  })();
</script>
<noscript><p><img src="//gheop.com/stats/piwik.php?idsite=2" style="border:0;" alt="" /></p></noscript> -->
<!-- End Piwik Code -->

