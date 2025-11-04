<?php

namespace Gheop\Reader;

/**
 * RSS/Atom feed discovery and validation
 */
class RSSDiscovery
{
    /**
     * Check if content is valid RSS/Atom
     *
     * @param string $content XML content to check
     * @return bool True if valid RSS/Atom, false otherwise
     */
    public static function isRSSContent(string $content): bool
    {
        if (empty($content)) {
            return false;
        }

        try {
            $rss = @new \SimpleXMLElement($content);
        } catch (\Exception $e) {
            return false;
        }

        // Check for RSS channel items
        if (isset($rss->channel->item) && $rss->channel->item->count() > 0) {
            return true;
        }

        // Check for RSS items (without channel)
        if (isset($rss->item) && $rss->item->count() > 0) {
            return true;
        }

        // Check for Atom entries
        if (isset($rss->entry) && $rss->entry->count() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Detect special site patterns and return RSS feed URL
     *
     * @param string $url Original URL
     * @return string|false RSS feed URL or false if not a special site
     */
    public static function getSpecialSiteFeedUrl(string $url)
    {
        // YouTube channel
        if (preg_match('/^.*\/\/www\.youtube\.com\/channel\/(.*)$/', $url, $m)) {
            return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $m[1];
        }

        // YouTube user (legacy)
        if (preg_match('/^.*\/\/www\.youtube\.com\/user\/([^\?\&\/]*)(.*)$/', $url, $m)) {
            // Would need HTML parsing to get channel ID - return pattern for testing
            return 'youtube_user:' . $m[1];
        }

        // YouTube video
        if (preg_match('/^.*\/\/(www\.|m\.)?(youtube\.com|youtu.be|youtube-nocookie\.com)\/(watch\?.*\&?v=|embed\/|)([^\?\&]*)(.*)$/', $url, $m)) {
            return 'youtube_video:' . $m[4];
        }

        // Dailymotion short URL
        if (preg_match('/^.*dai\.ly\/(.*)$/', $url, $m)) {
            return self::getSpecialSiteFeedUrl('http://www.dailymotion.com/video/' . $m[1]);
        }

        // Dailymotion video
        if (preg_match('/^.*\/\/(www\.)?dailymotion\.com\/(embed\/)?video\/([^\?]*)(.*)?$/', $url, $m)) {
            return 'dailymotion_video:' . $m[3];
        }

        // Dailymotion user
        if (preg_match('/^.*\/\/www\.dailymotion\.com\/([^\/\?].*)$/', $url, $m)) {
            return 'http://www.dailymotion.com/rss/user/' . $m[1];
        }

        // Twitter
        if (preg_match('/^.*\/\/(www\.)?twitter\.com\/(.*)?\/?.*$/', $url, $m)) {
            return 'https://reader.gheop.com/scraping/twitter.com.php?f=' . $m[2];
        }

        // Reddit
        if (preg_match('/^.*\/\/(www\.)?reddit\.com\/(.*)?$/', $url, $m)) {
            return $m[0] . '.rss';
        }

        // Medium subdomain
        if (preg_match('/^.*\/\/(.*)\.medium\.com\/(.*)?$/', $url, $m)) {
            if (isset($m[1]) && $m[1] != 'www') {
                return 'https://' . $m[1] . '.medium.com/feed';
            }
            return false;
        }

        return false;
    }

    /**
     * Validate and normalize URL
     *
     * @param string $url URL to validate
     * @return string|false Validated URL or false if invalid
     */
    public static function validateUrl(string $url)
    {
        if (empty($url)) {
            return false;
        }

        // Add protocol if missing
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = '//' . $url;
        }

        // Sanitize URL
        $url = filter_var($url, FILTER_SANITIZE_URL);

        // Validate URL
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return $url;
    }

    /**
     * Extract feed metadata from RSS/Atom content
     *
     * @param \SimpleXMLElement $rss RSS/Atom feed
     * @return array Feed metadata [title, link, description, language]
     */
    public static function extractFeedMetadata(\SimpleXMLElement $rss): array
    {
        $metadata = [
            'title' => '',
            'link' => '',
            'description' => '',
            'language' => ''
        ];

        // Extract title
        if (isset($rss->channel->title)) {
            $metadata['title'] = (string)$rss->channel->title;
        } elseif (isset($rss->title)) {
            $metadata['title'] = (string)$rss->title;
        }

        // Extract link
        if (isset($rss->channel->link)) {
            $metadata['link'] = (string)$rss->channel->link;
        } elseif (isset($rss->link[0]['href'])) {
            $metadata['link'] = (string)$rss->link[0]['href'];
        }

        // Extract description
        if (isset($rss->channel->description)) {
            $metadata['description'] = (string)$rss->channel->description;
        } elseif (isset($rss->subtitle)) {
            $metadata['description'] = (string)$rss->subtitle;
        }

        // Extract language
        if (isset($rss->channel->language)) {
            $metadata['language'] = (string)$rss->channel->language;
        } elseif (isset($rss->language)) {
            $metadata['language'] = (string)$rss->language;
        }

        return $metadata;
    }

    /**
     * Complete relative link to absolute URL
     *
     * @param string $link Link to complete
     * @param string $baseUrl Base URL for relative links
     * @return string Absolute URL
     */
    public static function completeLink(string $link, string $baseUrl): string
    {
        // Already absolute
        if (preg_match('/^https?:\/\//', $link)) {
            return $link;
        }

        $parsedUrl = parse_url($baseUrl);
        if (!isset($parsedUrl['scheme'])) {
            $parsedUrl['scheme'] = 'https';
        }

        // Handle absolute path
        if (substr($link, 0, 1) === '/') {
            return $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $link;
        }

        // Handle relative path
        return $baseUrl . '/' . $link;
    }

    /**
     * Parse Twitter-style handles (@user or #hashtag)
     *
     * @param string $input Input string
     * @return array|null Array with ['type' => 'twitter', 'handle' => string] or null
     */
    public static function parseTwitterHandle(string $input): ?array
    {
        if (preg_match('/^[@#](.*)$/', $input, $m)) {
            return [
                'type' => 'twitter',
                'handle' => $m[1]
            ];
        }

        return null;
    }

    /**
     * Check if feed metadata is valid for insertion
     *
     * @param array $metadata Feed metadata
     * @return bool True if valid, false otherwise
     */
    public static function isValidFeedMetadata(array $metadata): bool
    {
        return !empty($metadata['title']) &&
               !empty($metadata['link']);
    }

    /**
     * Determine if title should be prefixed with space (for sorting)
     *
     * @param string $rssUrl RSS feed URL
     * @return bool True if should be prefixed, false otherwise
     */
    public static function shouldPrefixTitle(string $rssUrl): bool
    {
        // GitHub feeds
        if (preg_match('/^https:\/\/github.com\/.*/', $rssUrl)) {
            return true;
        }

        // YouTube feeds
        if (preg_match('/^https?:\/\/(.*)?youtube.com\/.*/', $rssUrl)) {
            return true;
        }

        return false;
    }
}
