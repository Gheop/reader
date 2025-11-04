<?php

namespace Gheop\Reader;

/**
 * Feed URL and data validator
 */
class FeedValidator
{
    /**
     * Validate feed URL format
     *
     * @param string $url
     * @return bool
     */
    public static function isValidFeedUrl(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        // Must be HTTP or HTTPS
        if (!preg_match('/^https?:\/\/.+/', $url)) {
            return false;
        }

        // Parse URL
        $parsed = parse_url($url);
        if ($parsed === false || !isset($parsed['host'])) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize feed title
     *
     * @param string $title
     * @return string
     */
    public static function sanitizeTitle(string $title): string
    {
        // Remove control characters
        $title = preg_replace('/[\x00-\x1F\x7F]/', '', $title);

        // Trim whitespace
        $title = trim($title);

        // Limit length
        if (mb_strlen($title) > 255) {
            $title = mb_substr($title, 0, 255);
        }

        return $title;
    }

    /**
     * Validate and sanitize feed data
     *
     * @param array $feedData
     * @return array|null Returns sanitized data or null if invalid
     */
    public static function validateFeedData(array $feedData): ?array
    {
        // Required fields
        if (!isset($feedData['rss']) || !self::isValidFeedUrl($feedData['rss'])) {
            return null;
        }

        // Sanitize title
        $title = isset($feedData['title']) ? self::sanitizeTitle($feedData['title']) : '';
        if (empty($title)) {
            $title = 'Untitled Feed';
        }

        // Validate link
        $link = $feedData['link'] ?? '';
        if (!self::isValidFeedUrl($link)) {
            $link = $feedData['rss'];
        }

        // Sanitize description
        $description = isset($feedData['description']) ? strip_tags($feedData['description']) : '';
        $description = mb_substr($description, 0, 1000);

        return [
            'title' => $title,
            'description' => $description,
            'rss' => $feedData['rss'],
            'link' => $link,
            'language' => $feedData['language'] ?? '',
        ];
    }

    /**
     * Detect feed type from content
     *
     * @param string $content
     * @return string 'rss'|'atom'|'unknown'
     */
    public static function detectFeedType(string $content): string
    {
        if (stripos($content, '<rss') !== false) {
            return 'rss';
        }

        if (stripos($content, '<feed') !== false && stripos($content, 'xmlns="http://www.w3.org/2005/Atom"') !== false) {
            return 'atom';
        }

        return 'unknown';
    }

    /**
     * Extract domain from URL
     *
     * @param string $url
     * @return string
     */
    public static function extractDomain(string $url): string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }
}
