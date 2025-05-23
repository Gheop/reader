<?php
// --- CONFIGURATION ---
$blogUrl  = 'https://www.anisayari.com/blog';
$baseUrl  = 'https://www.anisayari.com';
date_default_timezone_set('Europe/Paris');

// --- FONCTION D’AIDE : charger une page et retourner son HTML ---
function fetchHtml(string $url): ?string {
    // Vous pouvez remplacer par cURL si allow_url_fopen = Off
    $opts = ['http'=>['header'=>"User-Agent: PHP-RSS-Script\r\n"]];
    $context = stream_context_create($opts);
    return @file_get_contents($url, false, $context) ?: null;
}

// --- FONCTION D’EXTRACTION DU PREMIER PARAGRAPHE ---
function fetchExcerpt(string $url): string {
    $html = fetchHtml($url);
    if (!$html) return '';
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();
    $ps = $dom->getElementsByTagName('p');
    if ($ps->length === 0) return '';
    $text = trim($ps->item(0)->textContent);
    // Tronquer raisonnablement
    return mb_strlen($text) > 200 ? mb_substr($text, 0, 197).'...' : $text;
}

// --- MAP FRANÇAIS → ANGLAIS POUR PARSER LES DATES ---
$monthMap = [
    'janvier'=>'January','février'=>'February','mars'=>'March',
    'avril'=>'April','mai'=>'May','juin'=>'June',
    'juillet'=>'July','août'=>'August','septembre'=>'September',
    'octobre'=>'October','novembre'=>'November','décembre'=>'December',
];

// --- RÉCUPÉRATION DE LA LISTE DES ARTICLES ---
$html = fetchHtml($blogUrl);
if (!$html) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Impossible de récupérer la page de blog.');
}

libxml_use_internal_errors(true);
$dom   = new DOMDocument();
$dom->loadHTML($html);
libxml_clear_errors();
$xpath = new DOMXPath($dom);

// Sélecteur → tous les <a href="/blog/...">
$nodes = $xpath->query('//a[starts-with(@href, "/blog/")]');

$items = [];
foreach ($nodes as $node) {
    $href = $node->getAttribute('href');
    $link = $baseUrl . $href;
    $raw  = trim($node->textContent);

    // Séparer date & titre via regex
    if (preg_match('/^(\d{1,2}\s\p{L}+\s\d{4})\s+(.+)$/u', $raw, $m)) {
        [$toto , $dateFr, $title] = $m;
        // ex. "11 avril 2025"
        [$day, $monthFr, $year] = preg_split('/\s+/', $dateFr);
        $monthEn = $monthMap[mb_strtolower($monthFr)] ?? '';
        $dt = DateTime::createFromFormat(
            'j F Y', "$day $monthEn $year",
            new DateTimeZone('Europe/Paris')
        );
        $pubDate = $dt ? $dt->format(DateTime::RSS) : '';
    } else {
        $title   = $raw;
        $pubDate = '';
    }

    $items[] = [
        'title'       => $title,
        'link'        => $link,
        'pubDate'     => $pubDate,
        'description' => fetchExcerpt($link),
    ];
}

// --- CONSTRUCTION DU FLUX RSS ---
//header('Content-Type: application/rss+xml; charset=utf-8');

$rssDom = new DOMDocument('1.0', 'UTF-8');
$rss   = $rssDom->createElement('rss');

$rss->setAttribute('version', '2.0');
$rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
/*$rss->setAttribute('href', 'https://reader.gheop.com/scraping/anisayari.com.php');
$rss->setAttribute('rel', 'self');*/

$rssDom->appendChild($rss);

$channel = $rssDom->createElement('channel');
$rss->appendChild($channel);

// Métadonnées du canal
function addTextNode(DOMDocument $dom, DOMElement $parent, string $name, string $value) {
    $n = $dom->createElement($name);
    $n->appendChild($dom->createTextNode($value));
    $parent->appendChild($n);
}

addTextNode($rssDom, $channel, 'title',       'Anis Ayari - Blog');
addTextNode($rssDom, $channel, 'link',        $blogUrl);
addTextNode($rssDom, $channel, 'description', 'Flux RSS généré automatiquement pour le blog d\'Anis Ayari.');
addTextNode($rssDom, $channel, 'language',    'fr-fr');
addTextNode($rssDom, $channel, 'pubDate',     gmdate(DATE_RSS));

// Items
foreach ($items as $it) {
    $item = $rssDom->createElement('item');

    addTextNode($rssDom, $item, 'title', $it['title']);
    addTextNode($rssDom, $item, 'link',  $it['link']);
    addTextNode($rssDom, $item, 'guid',  $it['link']);
    if ($it['pubDate']) {
        addTextNode($rssDom, $item, 'pubDate', $it['pubDate']);
    }

    // description en CDATA
    $descNode = $rssDom->createElement('description');
    $cdata    = $rssDom->createCDATASection($it['description']);
    $descNode->appendChild($cdata);
    $item->appendChild($descNode);

    $channel->appendChild($item);
}

// Sortie
echo $rssDom->saveXML();
