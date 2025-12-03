<?php
// Security Headers
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Content Security Policy - comprehensive protection
$csp = [
    "default-src 'self'",
    "script-src 'self'",
    "script-src-attr 'unsafe-inline'", // Allow inline event handlers (onclick, etc.)
    "style-src 'self' 'unsafe-inline'", // unsafe-inline needed for dynamic theme switching
    "img-src 'self' data: https:",
    "font-src 'self' data:",
    "connect-src 'self'",
    "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com", // Allow YouTube embeds
    "frame-ancestors 'self'",
    "base-uri 'self'",
    "form-action 'self'",
    "upgrade-insecure-requests"
];
header("Content-Security-Policy: " . implode('; ', $csp));

// Permissions Policy - disable unused browser features
$permissions = [
    "geolocation=()",
    "microphone=()",
    "camera=()",
    "payment=()",
    "usb=()",
    "magnetometer=()",
    "gyroscope=()",
    "accelerometer=()",
    "ambient-light-sensor=()",
    "autoplay=()",
    "encrypted-media=()",
    "picture-in-picture=()"
];
header("Permissions-Policy: " . implode(', ', $permissions));

header('Content-Type: text/html; charset=utf-8');

include(__DIR__ . '/config/conf.php');
include(__DIR__ . '/config/auth.php');

// Use minified assets in production, original files in debug mode
$useMinified = !isset($_GET['debug']);
$jsFile = $useMinified ? 'assets/js/lib.min.js' : 'assets/js/lib.js';
$cssExt = $useMinified ? '.min.css' : '.css';
// Cache busting - use file modification time to bust cache on changes
$cacheBuster = '?v=' . filemtime($jsFile);

// Subresource Integrity (SRI) hashes for security
$sriHashes = [
    'assets/js/lib.min.js' => 'sha384-g1F3AjqVkumpjErI8CdsciDR/eugJVdvYLklD8BbNsJadwqt7YS1cmLbjAfggDPC',
    'assets/js/favico.min.js' => 'sha384-Wld99sh+AF8uAaf89VTlOSXGb5nMw9OIVxXywbRTHrv/G3LvqWLyZC24rPr/b9os',
    'themes/common.min.css' => 'sha384-uF1+R8I6nmJm3i8R7xu5eKO35EyKguvFgITVFoq16l+JIJzHJSgbxkWCes1dUZyW',
    'themes/light.min.css' => 'sha384-K1mHexSQvND0Y7cRn5jOhHbVwn5w1BI2DEqUdqM6iGoD0RHWKbZ2DpCdPUQaz8is',
    'themes/dark.min.css' => 'sha384-7HsYmfNgJ9+ySlQa7keqLZda8bZaw/87gg8wdBOMb2Z++ZwAkv2rpBYTu30CZx9o',
    'themes/adaptive.min.css' => 'sha384-Sen3SWx5Zhz4F+gziVIfX+rRulX/70cHfiDxGLcIftcEUXN2Og7Ro4jUcrKgFiRZ',
    'themes/adaptive-smooth.min.css' => 'sha384-c81nycqtM0OqyFtDoWcYJ1Ik7AWTqtxdlEKCB80cfjfQTLPdmkVv/KegmU7v3C63',
    'themes/modern.min.css' => 'sha384-WzdZG8/mSE1int8rel7jIV8DPoOgKTYmSs5Fwo+Z881q8LwDb5DoQ9jCpKxzoUbw',
    'fontawesome/css/all.min.css' => 'sha384-t1nt8BQoYMLFN5p42tRAtuAAFQaCQODekUVeKKZrEnEyp4H2R0RHFz0KWpmj7i8g'
];

// Get SRI attribute helper
function getSRI($file, $sriHashes, $useMinified) {
    return ($useMinified && isset($sriHashes[$file])) ? ' integrity="' . $sriHashes[$file] . '" crossorigin="anonymous"' : '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<link rel="preload" href="themes/common<?php echo $cssExt . $cacheBuster; ?>" as="style"<?php echo getSRI('themes/common' . $cssExt, $sriHashes, $useMinified); ?>>
<link rel="preload" href="themes/light<?php echo $cssExt . $cacheBuster; ?>" as="style"<?php echo getSRI('themes/light' . $cssExt, $sriHashes, $useMinified); ?>>
<link rel="preload" href="fontawesome/css/all.min.css" as="style"<?php echo getSRI('fontawesome/css/all.min.css', $sriHashes, $useMinified); ?>>
<link rel="stylesheet" href="fontawesome/css/all.min.css"<?php echo getSRI('fontawesome/css/all.min.css', $sriHashes, $useMinified); ?>>
<link rel="stylesheet" type="text/css" href="themes/common<?php echo $cssExt . $cacheBuster; ?>" media="screen"<?php echo getSRI('themes/common' . $cssExt, $sriHashes, $useMinified); ?> />
<link rel="stylesheet prefetch" id="stylesheet" type="text/css" href="themes/light<?php echo $cssExt . $cacheBuster; ?>" media="screen" title="Normal"<?php echo getSRI('themes/light' . $cssExt, $sriHashes, $useMinified); ?> />
<title>Gheop Reader</title>
<script src="assets/js/background-sync.js" defer></script>
<script src="assets/js/favico.min.js"<?php echo getSRI('assets/js/favico.min.js', $sriHashes, $useMinified); ?> defer></script>
<script src="<?php echo $jsFile . $cacheBuster; ?>"<?php echo getSRI($jsFile, $sriHashes, $useMinified); ?> type="text/javascript" defer></script>
<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
<link id="favico" href="favicon.png" rel="shortcut icon" type="image/png" />
<link rel="manifest" href="/assets/images/manifest.json">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title" content="ƒlux∑">
<meta name="theme-color" content="#2c3e50">
<meta name="description" content="Agrégateur RSS moderne avec support offline - Suivez vos flux RSS, Twitter, eBay et plus encore !">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<!-- iOS Splash Screens -->
<link rel="apple-touch-startup-image" media="screen and (device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)" href="splash-1170x2532.png">
<link rel="apple-touch-startup-image" media="screen and (device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)" href="splash-1170x2532.png">
<link rel="apple-touch-startup-image" media="screen and (device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)" href="splash-1170x2532.png">
<link rel="apple-touch-startup-image" media="screen and (device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)" href="splash-1125x2436.png">
<link rel="apple-touch-startup-image" media="screen and (device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)" href="splash-828x1792.png">
<link rel="apple-touch-startup-image" media="screen and (device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)" href="splash-2048x2732.png">

</head>
<body>
<?php
// Handle logout
if(isset($_GET['a']) && $_GET['a'] == 'destroy') {
    header('Location: logout.php');
    exit;
}
?>
<div id="register">
  <div id="offline-indicator" style="display:none; padding: 5px 10px; background: #ff9800; color: white; border-radius: 3px; margin-right: 10px; font-size: 12px;">
    <i class="fa fa-wifi" style="text-decoration: line-through;"></i> Mode hors ligne
  </div>
  <div id="theme-selector">
    <a id="theme-current" href="#" onclick="toggleThemeDropdown();return false;"></a>
    <div id="theme-dropdown" class="theme-dropdown-hidden">
      <a href="#" onclick="selectTheme('auto');return false;" data-theme="auto"><i class="theme-icon-auto"></i> Auto</a>
      <a href="#" onclick="selectTheme('light');return false;" data-theme="light"><i class="theme-icon-light"></i> Clair</a>
      <a href="#" onclick="selectTheme('dark');return false;" data-theme="dark"><i class="theme-icon-dark"></i> Sombre</a>
      <a href="#" onclick="selectTheme('adaptive');return false;" data-theme="adaptive"><i class="theme-icon-adaptive"></i> Adaptatif</a>
      <a href="#" onclick="selectTheme('smooth');return false;" data-theme="smooth"><i class="theme-icon-smooth"></i> Progressif</a>
      <a href="#" onclick="selectTheme('modern');return false;" data-theme="modern"><i class="theme-icon-modern"></i> Moderne</a>
    </div>
  </div>
<?php
if(!isset($_SESSION['pseudo'])) {
  echo '<a href="register.php">[S\'enregister]</a> - <a href="login.php">[S\'identifier]</a>';
}
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
error_log("[INDEX] Checking auth - user_id isset: " . (isset($_SESSION['user_id']) ? 'YES' : 'NO') . ", value: " . ($_SESSION['user_id'] ?? 'NOT SET'));
if(isset($_SESSION['user_id'])) {
error_log("[INDEX] User is logged in - showing main interface");
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
<footer>&nbsp;</footer>';
}
else {
  echo '<h2>Suivez l\'actualité de tous vos sites et blogs préférés.</h2>';

  // OAuth2 Login Buttons
  echo '<div style="text-align: center; margin: 30px 0;">
    <p style="margin-bottom: 20px; font-size: 1.1em;">Connexion rapide avec :</p>
    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
      <a href="oauth_login.php?provider=google" style="display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: #4285f4; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; transition: background 0.3s;">
        <svg width="18" height="18" xmlns="http://www.w3.org/2000/svg"><path d="M9 3.48c1.69 0 2.83.73 3.48 1.34l2.54-2.48C13.46.89 11.43 0 9 0 5.48 0 2.44 2.02.96 4.96l2.91 2.26C4.6 5.05 6.62 3.48 9 3.48z" fill="#EA4335"/><path d="M17.64 9.2c0-.74-.06-1.28-.19-1.84H9v3.34h4.96c-.1.83-.64 2.08-1.84 2.92l2.84 2.2c1.7-1.57 2.68-3.88 2.68-6.62z" fill="#4285F4"/><path d="M3.88 10.78A5.54 5.54 0 0 1 3.58 9c0-.62.11-1.22.29-1.78L.96 4.96A9.008 9.008 0 0 0 0 9c0 1.45.35 2.82.96 4.04l2.92-2.26z" fill="#FBBC05"/><path d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.84-2.2c-.76.53-1.78.9-3.12.9-2.38 0-4.4-1.57-5.12-3.74L.97 13.04C2.45 15.98 5.48 18 9 18z" fill="#34A853"/><path fill="none" d="M0 0h18v18H0z"/></svg>
        Google
      </a>
      <a href="oauth_login.php?provider=github" style="display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: #24292e; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; transition: background 0.3s;">
        <svg width="18" height="18" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M9 0C4.03 0 0 4.03 0 9c0 3.98 2.58 7.35 6.16 8.54.45.08.62-.2.62-.43v-1.5c-2.51.55-3.04-1.21-3.04-1.21-.41-1.04-1-1.32-1-1.32-.82-.56.06-.55.06-.55.9.06 1.38.93 1.38.93.8 1.38 2.1.98 2.62.75.08-.58.31-.98.57-1.2-2-.23-4.1-1-4.1-4.45 0-.98.35-1.79.92-2.42-.09-.23-.4-1.14.09-2.38 0 0 .75-.24 2.46.92a8.5 8.5 0 0 1 4.48 0c1.71-1.16 2.46-.92 2.46-.92.49 1.24.18 2.15.09 2.38.57.63.92 1.44.92 2.42 0 3.46-2.1 4.22-4.1 4.45.32.28.61.83.61 1.67v2.48c0 .24.17.52.62.43C15.42 16.35 18 12.98 18 9c0-4.97-4.03-9-9-9z"/></svg>
        GitHub
      </a>
      <a href="oauth_login.php?provider=twitter" style="display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: #000000; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; transition: background 0.3s;">
        <svg width="18" height="18" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        X (Twitter)
      </a>
    </div>
    <p style="margin: 25px 0 15px; color: #666;">ou</p>
    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
      <a href="login.php" style="display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; transition: background 0.3s;">
        <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/><path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/></svg>
        Se connecter
      </a>
      <a href="register.php" style="display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; background: #764ba2; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; transition: background 0.3s;">
        <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/></svg>
        S\'inscrire
      </a>
    </div>
  </div>';

  echo '<fieldset><legend>Simple</legend><br />Gheop Reader récupère en permance les nouveautés de tous vos sites favoris grâce à leur flux RSS et Atom.<br />Totalement gratuit et libre, un simple navigateur vous permet de suivre toute votre actualité de partout sans rien installer.<br /><br /></fieldset><br /><br /><fieldset><legend>Comment faire ?</legend><br />Il suffit de vous inscrire ou de vous identifier sur Gheop, d\'ajouter ou d\'importer vos fluxs et c\'est parti !<br /><br /></fieldset><br /><br /><fieldset><legend>Et ma vie privée dans tout ça ?</legend><br />Vous pouvez quitter Gheop Reader dès que vous le souhaitez, récupérer vos données sans rien perdre, voir même héberger votre Gheop Reader chez vous pour être totalement indépendant.<br /><br /></fieldset>';
}
?>

  </body>
</html>
