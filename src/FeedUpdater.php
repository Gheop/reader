<?php

namespace Gheop\Reader;

/**
 * Helper for feed update and link processing logic
 */
class FeedUpdater
{
    /**
     * Complete relative URLs to absolute URLs
     *
     * @param string|null $link Link to complete
     * @param string $linkmaster Master link (base URL)
     * @return string|null Completed link
     */
    public static function completeLink(?string $link, string $linkmaster): ?string
    {
        if (!isset($link) || !preg_match('/^https?:\/\//', $link)) {
            if (isset($link) && substr($link, 0, 1) == '/') {
                $pu = parse_url($linkmaster);
                if (!isset($pu['scheme'])) {
                    $pu['scheme'] = 'https';
                }
                if (substr($link, 1, 1) == '/') {
                    $link = $pu['scheme'] . ':' . $link;
                } else {
                    $link = $pu['scheme'] . '://' . $pu['host'] . $link;
                }
            } elseif (isset($link)) {
                $link = $linkmaster . '/' . $link;
            }
        }
        return $link;
    }

    /**
     * Clean and sanitize article link
     *
     * @param string $link Link to clean
     * @return string Cleaned link
     */
    public static function cleanArticleLink(string $link): string
    {
        $a = array(')', '(', '"', '\\');
        $b = array('', '', '', '\\\\');
        return str_replace($a, $b, $link);
    }

    /**
     * Extract link without protocol for comparison
     *
     * @param string $link Full link
     * @return string Link without protocol
     */
    public static function getLinkWithoutProtocol(string $link): string
    {
        return preg_replace('/^https?/', '', $link);
    }

    /**
     * Parse date from RSS/Atom feed item
     *
     * @param string|null $dateString Date string from feed
     * @return int Unix timestamp
     */
    public static function parseFeedDate(?string $dateString): int
    {
        if (empty($dateString)) {
            return time();
        }

        try {
            $date = new \DateTime($dateString);
            $timestamp = $date->getTimestamp();

            // Validate timestamp is not in the future
            if ($timestamp > time()) {
                return time();
            }

            return $timestamp;
        } catch (\Exception $e) {
            return time();
        }
    }

    /**
     * Determine feed type from SimpleXML object
     *
     * @param \SimpleXMLElement $rss RSS/Atom feed object
     * @return string Feed type: 'rss', 'atom', or 'unknown'
     */
    public static function detectFeedType(\SimpleXMLElement $rss): string
    {
        if (isset($rss->channel->item)) {
            return 'rss';
        } elseif (isset($rss->item)) {
            return 'rss';
        } elseif (isset($rss->entry)) {
            return 'atom';
        }

        return 'unknown';
    }

    /**
     * Extract feed items from SimpleXML object
     *
     * @param \SimpleXMLElement $rss RSS/Atom feed object
     * @return \SimpleXMLElement[]|null Array of feed items or null
     */
    public static function extractFeedItems(\SimpleXMLElement $rss): ?array
    {
        if (isset($rss->channel->item)) {
            return iterator_to_array($rss->channel->item);
        } elseif (isset($rss->item)) {
            return iterator_to_array($rss->item);
        } elseif (isset($rss->entry)) {
            return iterator_to_array($rss->entry);
        }

        return null;
    }

    /**
     * Extract link from feed item
     *
     * @param \SimpleXMLElement $item Feed item
     * @return string|null Extracted link
     */
    public static function extractItemLink(\SimpleXMLElement $item): ?string
    {
        $link = null;

        // Check for direct link first (RSS-style)
        if (isset($item->link) && preg_match('/^https?:\/\//', (string)$item->link)) {
            $link = (string)$item->link;
            return $link;
        }

        // Check for object link with attributes (Atom-style)
        if (is_object($item->link) && isset($item->link[0])) {
            foreach ($item->link as $t) {
                if (isset($t['href'])) {
                    if ($t['rel'] == "alternate" || $t['rel'] == "self") {
                        $link = (string)$t['href'];
                        return $link;
                    }
                    if (!isset($link)) {
                        $link = (string)$t['href'];
                    }
                }
            }
        }

        // Check for guid as link
        if (!isset($link) && isset($item->guid) && preg_match('/^https?:\/\//', (string)$item->guid)) {
            $link = (string)$item->guid;
        }

        return $link;
    }

    /**
     * Extract content from feed item
     *
     * @param \SimpleXMLElement $item Feed item
     * @param string|null $link Item link for special cases
     * @return string|null Extracted content
     */
    public static function extractItemContent(\SimpleXMLElement $item, ?string $link = null): ?string
    {
        $content = null;

        if (isset($item->description)) {
            $content = (string)$item->description;
        } elseif (isset($item->content)) {
            $content = (string)$item->content;
        } elseif (isset($item->summary)) {
            $content = (string)$item->summary;
        } elseif ($link) {
            // Special case for YouTube
            if (preg_match('/^(.*\/\/)?(www.)?youtube.com\/watch\?v=(.*)/', $link, $m) ||
                preg_match('/^(.*\/\/)?(www.)?youtube.com\/shorts\/(.*)/', $link, $m)) {
                $content = '<yt>' . $m[3] . '</yt>';
            }
            // Special case for images
            elseif (preg_match('/^(\/\/.*\.(jpe?g|gif|png))/', $link, $m)) {
                $content = '<img src="' . $m[1] . '" />';
            }
        }

        return $content;
    }

    /**
     * Extract author from feed item
     *
     * @param \SimpleXMLElement $item Feed item
     * @return string Extracted author (empty string if not found)
     */
    public static function extractItemAuthor(\SimpleXMLElement $item): string
    {
        if (isset($item->author->name)) {
            return (string)$item->author->name;
        } elseif (isset($item->author)) {
            return (string)$item->author;
        }

        return '';
    }

    /**
     * Extract title from feed item
     *
     * @param \SimpleXMLElement $item Feed item
     * @return string|null Extracted title
     */
    public static function extractItemTitle(\SimpleXMLElement $item): ?string
    {
        if (isset($item->title)) {
            return (string)$item->title;
        }

        return null;
    }

    /**
     * Extract feed title from RSS/Atom feed
     *
     * @param \SimpleXMLElement $rss Feed object
     * @return string|null Extracted feed title
     */
    public static function extractFeedTitle(\SimpleXMLElement $rss): ?string
    {
        if (isset($rss->title)) {
            return (string)$rss->title;
        } elseif (isset($rss->channel->title)) {
            return (string)$rss->channel->title;
        }

        return null;
    }

    /**
     * Extract master link from feed
     *
     * @param \SimpleXMLElement $rss Feed object
     * @return string|null Extracted master link
     */
    public static function extractMasterLink(\SimpleXMLElement $rss): ?string
    {
        if (isset($rss->channel->link)) {
            return (string)$rss->channel->link;
        } elseif (isset($rss->link[0]['href'])) {
            return (string)$rss->link[0]['href'];
        }

        return null;
    }

    /**
     * Clean XML content before parsing
     *
     * @param string $xml Raw XML content
     * @return string Cleaned XML
     */
    public static function cleanXml(string $xml): string
    {
        $xml = trim($xml);

        // Extract only up to closing </rss> tag
        $xml = preg_replace('/^(.*<\/rss>).*$/s', '\\1', $xml);

        // Clean image URLs with query parameters
        $xml = preg_replace(
            '/url="(.*?\.(jpg|png|gif))\?.*?"/s',
            'url="$1"',
            $xml
        );

        // Remove empty type attributes
        $xml = preg_replace('/type=""/s', '', $xml);

        return $xml;
    }

    /**
     * Validate if URL redirected and return new URL
     *
     * @param string $originalUrl Original URL
     * @param string $effectiveUrl Effective URL after redirect
     * @return string|null New URL if redirected, null otherwise
     */
    public static function getRedirectedUrl(string $originalUrl, string $effectiveUrl): ?string
    {
        if ($originalUrl !== $effectiveUrl) {
            return $effectiveUrl;
        }

        return null;
    }

    /**
     * Check if item has valid link
     *
     * @param string|null $link Link to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidItemLink(?string $link): bool
    {
        return isset($link) && $link !== '' && $link !== null;
    }
}
