<?php

namespace Gheop\Reader;

use DOMDocument;

/**
 * OPML Generator for feed exports
 */
class OPMLGenerator
{
    /**
     * Generate OPML document from feeds array
     *
     * @param array $feeds Array of feed data
     * @return string OPML XML string
     */
    public static function generate(array $feeds): string
    {
        $opml = new DOMDocument('1.0', 'UTF-8');
        $opml->formatOutput = true;

        // Root element
        $root = $opml->createElement('opml');
        $root->setAttribute('version', '2.0');
        $opml->appendChild($root);

        // Head
        $head = $opml->createElement('head');
        $root->appendChild($head);

        $title = $opml->createElement('title', 'Gheop Reader Feeds Export');
        $head->appendChild($title);

        $dateCreated = $opml->createElement('dateCreated', date('r'));
        $head->appendChild($dateCreated);

        // Body
        $body = $opml->createElement('body');
        $root->appendChild($body);

        // Add each feed
        foreach ($feeds as $flux) {
            if (!self::addFeedToBody($opml, $body, $flux)) {
                continue;
            }
        }

        return $opml->saveXML();
    }

    /**
     * Add a single feed to the OPML body
     *
     * @param DOMDocument $opml
     * @param \DOMElement $body
     * @param array $flux Feed data
     * @return bool True if feed was added, false if skipped
     */
    private static function addFeedToBody(DOMDocument $opml, \DOMElement $body, array $flux): bool
    {
        // Validate and clean URLs
        $xmlUrl = trim($flux['rss'] ?? '');
        $htmlUrl = trim($flux['link'] ?? '');

        // Verify URLs are valid (start with http:// or https://)
        if (!self::isValidUrl($xmlUrl)) {
            return false;
        }

        // If htmlUrl is invalid or empty, use xmlUrl
        if (empty($htmlUrl) || !self::isValidUrl($htmlUrl)) {
            $htmlUrl = $xmlUrl;
        }

        $outline = $opml->createElement('outline');
        $outline->setAttribute('type', 'rss');
        $outline->setAttribute('text', htmlspecialchars($flux['title'] ?? '', ENT_XML1, 'UTF-8'));
        $outline->setAttribute('title', htmlspecialchars($flux['title'] ?? '', ENT_XML1, 'UTF-8'));
        $outline->setAttribute('xmlUrl', htmlspecialchars($xmlUrl, ENT_XML1, 'UTF-8'));
        $outline->setAttribute('htmlUrl', htmlspecialchars($htmlUrl, ENT_XML1, 'UTF-8'));

        if (!empty($flux['description'])) {
            $outline->setAttribute('description', htmlspecialchars($flux['description'], ENT_XML1, 'UTF-8'));
        }

        $body->appendChild($outline);
        return true;
    }

    /**
     * Validate URL format
     *
     * @param string $url
     * @return bool
     */
    public static function isValidUrl(string $url): bool
    {
        return preg_match('/^https?:\/\/.+/', $url) === 1;
    }

    /**
     * Generate filename for OPML export
     *
     * @param string|null $date Optional date string (Y-m-d format)
     * @return string
     */
    public static function getFilename(?string $date = null): string
    {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        return "gheop-reader-feeds-{$date}.opml";
    }
}
