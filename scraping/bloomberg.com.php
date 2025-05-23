<?php
// rss-bloomberg-latest-agg.php

// — CONFIGURATION —
date_default_timezone_set('Europe/Paris');
$categoryFeeds = [
    'Markets'    => 'https://feeds.bloomberg.com/markets/news.rss',
    'Politics'   => 'https://feeds.bloomberg.com/politics/news.rss',
    'Tech'       => 'https://feeds.bloomberg.com/technology/news.rss',
    'Business'   => 'https://feeds.bloomberg.com/business/news.rss',
    'Wealth'     => 'https://feeds.bloomberg.com/wealth/news.rss',
    'Economics'  => 'https://feeds.bloomberg.com/economics/news.rss',
    'Industries' => 'https://feeds.bloomberg.com/industries/news.rss',
    'Green'      => 'https://feeds.bloomberg.com/green/news.rss',
    'Crypto'     => 'https://feeds.bloomberg.com/crypto/news.rss',
    'Opinions'   => 'https://feeds.bloomberg.com/bview/news.rss',
];

// Nombre max d’items à exposer
$maxItems = 50;

// — CHARGEMENT & FUSION DES ITEMS —
$all = [];
foreach ($categoryFeeds as $cat => $feedUrl) {
    if (@$rss = simplexml_load_file($feedUrl)) {
        foreach ($rss->channel->item as $item) {
            $all[] = [
                'title'       => (string)$item->title,
                'link'        => (string)$item->link,
                'pubDate'     => new DateTime((string)$item->pubDate),
                'description' => (string)$item->description,
            ];
        }
    }
}

// tri décroissant sur pubDate
usort($all, function($a, $b){
    return $b['pubDate']->getTimestamp() - $a['pubDate']->getTimestamp();
});
$all = array_slice($all, 0, $maxItems);

// — GÉNÉRATION DU RSS —
header('Content-Type: application/rss+xml; charset=utf-8');

$dom = new DOMDocument('1.0','UTF-8');
$rss = $dom->createElement('rss');
$rss->setAttribute('version','2.0');
$dom->appendChild($rss);

$ch = $dom->createElement('channel');
$rss->appendChild($ch);

// helper
function addNode(DOMDocument $dom, DOMElement $parent, string $name, string $value) {
    $el = $dom->createElement($name);
    $el->appendChild($dom->createTextNode($value));
    $parent->appendChild($el);
}

addNode($dom, $ch, 'title',       'Bloomberg – Aggregated Latest');
addNode($dom, $ch, 'link',        'https://www.bloomberg.com/latest');
addNode($dom, $ch, 'description', 'Flux RSS agrégé des dernières à partir des catégories officielles.');
addNode($dom, $ch, 'language',    'en-us');
addNode($dom, $ch, 'pubDate',     gmdate(DATE_RSS));

foreach ($all as $it) {
    $item = $dom->createElement('item');
    addNode($dom, $item, 'title',       $it['title']);
    addNode($dom, $item, 'link',        $it['link']);
    addNode($dom, $item, 'guid',        $it['link']);
    addNode($dom, $item, 'pubDate',     $it['pubDate']->format(DateTime::RSS));

    $d = $dom->createElement('description');
    $d->appendChild($dom->createCDATASection($it['description']));
    $item->appendChild($d);

    $ch->appendChild($item);
}

echo $dom->saveXML();
