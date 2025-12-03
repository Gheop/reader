<?php
/**
 * Feed URL Detector for Gheop Reader
 *
 * Detects RSS/Atom feed URLs from various sources:
 * - YouTube channels, users, videos
 * - Twitter/Nitter profiles
 * - Reddit subreddits
 * - Generic HTML pages with link[rel=alternate]
 */

require_once(__DIR__ . '/../Http/HttpClient.php');

class FeedDetector {
    private HttpClient $http;
    private ?string $youtubeApiKey;

    public function __construct(?string $youtubeApiKey = null) {
        $this->http = new HttpClient();
        $this->youtubeApiKey = $youtubeApiKey;
    }

    /**
     * Detect RSS feed URL from any URL
     *
     * @param string $url Input URL
     * @return string|false Feed URL or false if not found
     */
    public function detect(string $url): string|false {
        // First try special site detection
        $feedUrl = $this->detectSpecialSite($url);
        if ($feedUrl) {
            return $feedUrl;
        }

        // Then try to find RSS link in HTML
        return $this->detectFromHtml($url);
    }

    /**
     * Detect feed URL for special sites (YouTube, Twitter, Reddit, etc.)
     */
    public function detectSpecialSite(string $url): string|false {
        // YouTube channel
        if (preg_match('/youtube\.com\/channel\/([^\/\?]+)/', $url, $m)) {
            return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $m[1];
        }

        // YouTube @handle
        if (preg_match('/youtube\.com\/@([^\/\?]+)/', $url, $m)) {
            return $this->getYouTubeChannelFromHandle($m[1]);
        }

        // YouTube user
        if (preg_match('/youtube\.com\/user\/([^\/\?]+)/', $url, $m)) {
            return $this->getYouTubeChannelFromUserPage($url);
        }

        // YouTube video - extract channel
        if (preg_match('/(?:youtube\.com\/(?:watch\?.*v=|embed\/|shorts\/)|youtu\.be\/)([^&\?\/]+)/', $url, $m)) {
            return $this->getYouTubeChannelFromVideo($m[1]);
        }

        // Twitter/X - use Nitter
        if (preg_match('/(?:twitter\.com|x\.com)\/([^\/\?]+)/', $url, $m)) {
            $username = $m[1];
            if (!in_array($username, ['home', 'explore', 'search', 'settings', 'i'])) {
                return 'https://nitter.net/' . $username . '/rss';
            }
        }

        // Reddit subreddit
        if (preg_match('/reddit\.com\/r\/([^\/\?]+)/', $url, $m)) {
            return 'https://www.reddit.com/r/' . $m[1] . '/.rss';
        }

        // Reddit user
        if (preg_match('/reddit\.com\/user\/([^\/\?]+)/', $url, $m)) {
            return 'https://www.reddit.com/user/' . $m[1] . '/.rss';
        }

        // GitHub releases
        if (preg_match('/github\.com\/([^\/]+)\/([^\/]+)/', $url, $m)) {
            return 'https://github.com/' . $m[1] . '/' . $m[2] . '/releases.atom';
        }

        return false;
    }

    /**
     * Detect feed URL from HTML page
     */
    public function detectFromHtml(string $url): string|false {
        $content = $this->http->get($url);
        if (!$content) {
            return false;
        }

        // Check if the URL itself is a feed
        if ($this->isValidFeed($content)) {
            return $url;
        }

        // Look for link[rel=alternate] with RSS/Atom type
        $patterns = [
            '/<link[^>]+type=["\']application\/rss\+xml["\'][^>]+href=["\']([^"\']+)["\']/',
            '/<link[^>]+href=["\']([^"\']+)["\'][^>]+type=["\']application\/rss\+xml["\']/',
            '/<link[^>]+type=["\']application\/atom\+xml["\'][^>]+href=["\']([^"\']+)["\']/',
            '/<link[^>]+href=["\']([^"\']+)["\'][^>]+type=["\']application\/atom\+xml["\']/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $m)) {
                $feedUrl = $m[1];
                // Handle relative URLs
                if (!preg_match('/^https?:\/\//', $feedUrl)) {
                    $feedUrl = $this->resolveRelativeUrl($feedUrl, $url);
                }
                return $feedUrl;
            }
        }

        // Try common feed paths
        $parsedUrl = parse_url($url);
        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        $commonPaths = ['/feed', '/rss', '/feed.xml', '/rss.xml', '/atom.xml', '/index.xml'];

        foreach ($commonPaths as $path) {
            $testUrl = $baseUrl . $path;
            $testContent = $this->http->get($testUrl);
            if ($testContent && $this->isValidFeed($testContent)) {
                return $testUrl;
            }
        }

        return false;
    }

    /**
     * Check if content is a valid RSS/Atom feed
     */
    public function isValidFeed(string $content): bool {
        if (empty($content)) {
            return false;
        }

        // Quick check for XML feed markers
        if (strpos($content, '<rss') === false &&
            strpos($content, '<feed') === false &&
            strpos($content, '<channel') === false) {
            return false;
        }

        try {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($content);
            libxml_clear_errors();

            if (!$xml) {
                return false;
            }

            // Check for RSS 2.0
            if (isset($xml->channel->item)) {
                return true;
            }

            // Check for RSS 1.0
            if (isset($xml->item)) {
                return true;
            }

            // Check for Atom
            if (isset($xml->entry)) {
                return true;
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get YouTube channel ID from @handle
     */
    private function getYouTubeChannelFromHandle(string $handle): string|false {
        $url = 'https://www.youtube.com/@' . $handle;
        $content = $this->http->get($url);

        if ($content && preg_match('/channel_id=([^"&]+)/', $content, $m)) {
            return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $m[1];
        }

        if ($content && preg_match('/"channelId":"([^"]+)"/', $content, $m)) {
            return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $m[1];
        }

        return false;
    }

    /**
     * Get YouTube channel ID from user page
     */
    private function getYouTubeChannelFromUserPage(string $url): string|false {
        $content = $this->http->get($url);

        if ($content && preg_match('/channel_id=([^"&]+)/', $content, $m)) {
            return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $m[1];
        }

        if ($content && preg_match('/"channelId":"([^"]+)"/', $content, $m)) {
            return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $m[1];
        }

        return false;
    }

    /**
     * Get YouTube channel ID from video ID using API
     */
    private function getYouTubeChannelFromVideo(string $videoId): string|false {
        if (!$this->youtubeApiKey) {
            // Fallback: scrape video page
            $url = 'https://www.youtube.com/watch?v=' . $videoId;
            $content = $this->http->get($url);

            if ($content && preg_match('/"channelId":"([^"]+)"/', $content, $m)) {
                return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $m[1];
            }

            return false;
        }

        // Use YouTube API
        $apiUrl = 'https://www.googleapis.com/youtube/v3/videos?part=snippet&id='
            . urlencode($videoId) . '&key=' . urlencode($this->youtubeApiKey);

        $response = $this->http->get($apiUrl);
        if (!$response) {
            return false;
        }

        $data = json_decode($response, true);
        if (isset($data['items'][0]['snippet']['channelId'])) {
            return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $data['items'][0]['snippet']['channelId'];
        }

        return false;
    }

    /**
     * Resolve relative URL to absolute
     */
    private function resolveRelativeUrl(string $relative, string $base): string {
        $parsed = parse_url($base);
        $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];

        if (str_starts_with($relative, '//')) {
            return $parsed['scheme'] . ':' . $relative;
        }

        if (str_starts_with($relative, '/')) {
            return $baseUrl . $relative;
        }

        $path = $parsed['path'] ?? '/';
        $path = dirname($path);

        return $baseUrl . $path . '/' . $relative;
    }
}
