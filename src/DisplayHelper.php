<?php

namespace Gheop\Reader;

/**
 * Helper for display and UI generation
 */
class DisplayHelper
{
    /**
     * Get branch display name with version
     *
     * @param string $gitHeadPath Path to .git/HEAD file
     * @param string|null $versionFile Path to version file
     * @return string Branch name with version or empty string
     */
    public static function getBranchDisplay(string $gitHeadPath, ?string $versionFile = null): string
    {
        if (!file_exists($gitHeadPath)) {
            return '';
        }

        $stringfromfile = file($gitHeadPath, FILE_USE_INCLUDE_PATH);
        if ($stringfromfile === false || empty($stringfromfile)) {
            return '';
        }

        $firstLine = $stringfromfile[0];
        $explodedstring = explode("/", $firstLine, 3);

        if (count($explodedstring) < 3) {
            return '';
        }

        $branchname = trim($explodedstring[2]);

        if ($versionFile && file_exists($versionFile)) {
            $version = file_get_contents($versionFile);
            if ($version !== false) {
                $branchname .= ' ' . trim($version);
            }
        }

        return $branchname;
    }

    /**
     * Check if branch should be displayed (not master)
     *
     * @param string $branchname Branch name
     * @return bool True if should be displayed, false otherwise
     */
    public static function shouldDisplayBranch(string $branchname): bool
    {
        return strpos($branchname, 'master') !== 0;
    }

    /**
     * Format branch badge HTML
     *
     * @param string $branchname Branch name
     * @return string HTML for branch badge
     */
    public static function formatBranchBadge(string $branchname): string
    {
        $escaped = htmlspecialchars($branchname, ENT_QUOTES, 'UTF-8');
        return "<span style='font-family: Helvetica; color: #d43f57; position: relative;bottom: 1px;font-size: .4em;line-height: .4em;vertical-align:super;text-decoration:none;'>$escaped</span>";
    }

    /**
     * Build navigation menu HTML
     *
     * @return string HTML for navigation menu
     */
    public static function buildNavigationMenu(): string
    {
        return '<nav>
  <ul id="menu">
    <li id="fall" class="fluxnew" onclick="view(\'all\')" title="Tout voir">All&nbsp;&nbsp;&nbsp;
    <span class="icon"><a title="Ajouter un flux" onclick="addflux();"></a>&nbsp;&nbsp;&nbsp;
    <a id="up" onclick="up();" title="Mettre à jour les flux"></a>&nbsp;&nbsp;&nbsp;
    <a id="export" href="opml_export.php" onclick="event.stopPropagation();" title="Exporter les flux (OPML)"></a>
    </span>
    </li>
  </ul>

</nav>';
    }

    /**
     * Build main content structure HTML
     *
     * @return string HTML for main content structure
     */
    public static function buildContentStructure(): string
    {
        return '  <div id="menu-resizer"></div>
<main>
</main>
<footer>
</footer>';
    }

    /**
     * Build unauthenticated welcome HTML
     *
     * @return string HTML for welcome message
     */
    public static function buildWelcomeMessage(): string
    {
        return '<h2>Suivez l\'actualité de tous vos sites et blogs préférés.</h2>' .
               '<fieldset><legend>Simple</legend><br />Gheop Reader récupère en permance les nouveautés de tous vos sites favoris grâce à leur flux RSS et Atom.<br />Totalement gratuit et libre, un simple navigateur vous permet de suivre toute votre actualité de partout sans rien installer.<br /><br /></fieldset>' .
               '<br /><br />' .
               '<fieldset><legend>Comment faire ?</legend><br />Il suffit de vous inscrire ou de vous identifier sur Gheop, d\'ajouter ou d\'importer vos fluxs et c\'est parti !<br /><br /></fieldset>' .
               '<br /><br />' .
               '<fieldset><legend>Et ma vie privée dans tout ça ?</legend><br />Vous pouvez quitter Gheop Reader dès que vous le souhaitez, récupérer vos données sans rien perdre, voir même héberger votre Gheop Reader chez vous pour être totalement indépendant.<br /><br /></fieldset>';
    }

    /**
     * Build search form HTML
     *
     * @return string HTML for search form
     */
    public static function buildSearchForm(): string
    {
        return '<div id="sdiv">
   <form onsubmit="search($(\'s\').value);return false;" >
   <input id="s" type="text" name="s" />
   <button id="bs" onclick="search($(\'s\').value);return false;"></button>
   </form>
</div>';
    }

    /**
     * Build user menu HTML
     *
     * @param string $pseudo User pseudo
     * @return string HTML for user menu
     */
    public static function buildUserMenu(string $pseudo): string
    {
        $escaped = htmlspecialchars($pseudo, ENT_QUOTES, 'UTF-8');
        return $escaped . ' <a id="disconnect" class="icon" href="?a=destroy" title="Se déconnecter"></a>';
    }

    /**
     * Build guest menu HTML
     *
     * @param string $currentPage Current page URL
     * @return string HTML for guest menu
     */
    public static function buildGuestMenu(string $currentPage): string
    {
        $registerUrl = AuthHelper::getRegistrationUrl($currentPage);
        $loginUrl = AuthHelper::getLoginUrl($currentPage);

        return '<a href="' . htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') . '">[S\'enregister]</a> - ' .
               '<a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">[S\'identifier]</a>';
    }

    /**
     * Build error display div
     *
     * @return string HTML for error div
     */
    public static function buildErrorDiv(): string
    {
        return '<div id="error" style="display:none;"></div>';
    }

    /**
     * Validate and sanitize CSS class name
     *
     * @param string $className Class name to validate
     * @return string Sanitized class name
     */
    public static function sanitizeClassName(string $className): string
    {
        // Allow only alphanumeric, dash, and underscore
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $className);
    }

    /**
     * Build HTML meta tags
     *
     * @return string HTML meta tags
     */
    public static function buildMetaTags(): string
    {
        return '<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
<link id="favico" href="favicon.png" rel="shortcut icon" type="image/png" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black" />
<meta name="description" content="Read and follow RSS, twitter, ebay, leboncoin ... and lot of more !">';
    }
}
