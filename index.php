<?php
//header("Content-Security-Policy: default-src 'self' https:;");
header("Content-Security-Policy: img-src 'self' data: https:;");
//header("Content-Security-Policy: img-src 'self' data:;");
//header("Content-Security-Policy: img-src: 'self' 'inline'");
//header("Content-Security-Policy: frame-ancestors 'none'");
header('Content-Type: text/html; charset=utf-8');
//header('X-Frame-Options: deny');
//header('X-Content-Type-Options: nosniff');
include('/www/conf.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" type="text/css" href="common.css" media="screen" /><!-- ?v=<?php echo time(); ?> -->
<link rel="stylesheet prefetch" id="stylesheet" type="text/css" href="screen.css" media="screen" title="Normal" /><!-- ?v=<?php echo time(); ?> -->
<title>Gheop Reader</title>
<script src="favico.min.js" defer></script>
<script src="https://reader.gheop.com/lib.js" type="text/javascript" defer></script><!-- ?v=<?php echo time(); ?> -->
<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
<link id="favico" href="favicon.png" rel="shortcut icon" type="image/png" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black" />
<meta name="description" content="Read and follow RSS, twitter, ebay, leboncoin ... and lot of more !">

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
  <a id='theme' href="" onclick="changeTheme();return false;"></a>
<?php
if(!isset($_SESSION['pseudo'])) {
  echo '<a href="//gheop.com/register/?page=reader.gheop.com">[S\'enregister]</a> - <a href="http://www.gheop.com/register/ident.php?page=reader.gheop.com">[S\'identifier]</a>';}
else {
  echo $_SESSION['pseudo'],' <a id="disconnect" class="icon" href="?a=destroy" title="Se déconnecter"></a>';

}
?>
</div>
    <h1 id="g"><a href="//reader.gheop.com/" title="ƒlux∑"><span id="g1">ƒlux∑</span><span id="g2"><!-- reader --></span>
<?php  if(isset($_SESSION['pseudo']) && $_SESSION['pseudo'] == "SiB") {
  	$stringfromfile = file('.git/HEAD', FILE_USE_INCLUDE_PATH);
    $firstLine = $stringfromfile[0];
    $explodedstring = explode("/", $firstLine, 3);
    $branchname = trim($explodedstring[2]);
    if(file_exists('version')) $branchname .= ' '.file_get_contents('version');
    if(strpos($branchname, 'master') !== 0)    echo "<span style='font-family: Helvetica; color: #d43f57; position: relative;bottom: 1px;font-size: .4em;line-height: .4em;vertical-align:super;text-decoration:none;'>" . $branchname . "</span>";
  }
  ?>
  </a></h1>
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
echo '<nav>
  <ul id="menu">
    <li id="fall" class="fluxnew" onclick="view(\'all\')" title="Tout voir">All&nbsp;&nbsp;&nbsp;
    <span class="icon"><a title="Ajouter un flux" onclick="addflux();"></a>&nbsp;&nbsp;&nbsp;
    <a id="up" onclick="up();" title="Mettre à jour les flux"></a>&nbsp;&nbsp;&nbsp;
    <a id="export" href="opml_export.php" onclick="event.stopPropagation();" title="Exporter les flux (OPML)"></a>
    </span>
    </li>
  </ul>

</nav>
  <div id="menu-resizer"></div>
<main>
</main>
<footer>
</footer>';
//<script type="text/javascript" nonce="EDNnf03nceIOfn39fn3e9h3sdfa">i();</script>';
}
else {
  echo '<h2>Suivez l\'actualité de tous vos sites et blogs préférés.</h2><fieldset><legend>Simple</legend><br />Gheop Reader récupère en permance les nouveautés de tous vos sites favoris grâce à leur flux RSS et Atom.<br />Totalement gratuit et libre, un simple navigateur vous permet de suivre toute votre actualité de partout sans rien installer.<br /><br /></fieldset><br /><br /><fieldset><legend>Comment faire ?</legend><br />Il suffit de vous inscrire ou de vous identifier sur Gheop, d\'ajouter ou d\'importer vos fluxs et c\'est parti !<br /><br /></fieldset><br /><br /><fieldset><legend>Et ma vie privée dans tout ça ?</legend><br />Vous pouvez quitter Gheop Reader dès que vous le souhaitez, récupérer vos données sans rien perdre, voir même héberger votre Gheop Reader chez vous pour être totalement indépendant.<br /><br /></fieldset>';
}
?>

  </body>
</html>
