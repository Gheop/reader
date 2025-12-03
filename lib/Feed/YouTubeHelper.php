<?php
/**
 * YouTube Helper for Gheop Reader
 *
 * Provides YouTube-specific functionality:
 * - Fetch video descriptions via API
 * - Extract video metadata
 * - Add YouTube icon to feed titles
 */

require_once(__DIR__ . '/../Http/HttpClient.php');

class YouTubeHelper {
    private HttpClient $http;
    private ?string $apiKey;
    private static string $youtubeIcon = "\xef\x85\xa7"; // Font Awesome YouTube icon (U+F167)

    public function __construct(?string $apiKey = null) {
        $this->http = new HttpClient();
        $this->apiKey = $apiKey;
    }

    /**
     * Get video description from YouTube API
     *
     * @param string $videoId YouTube video ID
     * @return string|null Video description or null if not available
     */
    public function getVideoDescription(string $videoId): ?string {
        if (!$this->apiKey) {
            return null;
        }

        $apiUrl = 'https://www.googleapis.com/youtube/v3/videos?part=snippet&id='
            . urlencode($videoId) . '&key=' . urlencode($this->apiKey);

        $response = $this->http->get($apiUrl);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (!isset($data['items'][0]['snippet']['description'])) {
            return null;
        }

        return $data['items'][0]['snippet']['description'];
    }

    /**
     * Get multiple video descriptions in batch
     *
     * @param array $videoIds Array of video IDs
     * @return array Associative array [videoId => description]
     */
    public function getVideoDescriptions(array $videoIds): array {
        if (!$this->apiKey || empty($videoIds)) {
            return [];
        }

        $results = [];

        // YouTube API allows up to 50 video IDs per request
        $chunks = array_chunk($videoIds, 50);

        foreach ($chunks as $chunk) {
            $ids = implode(',', $chunk);
            $apiUrl = 'https://www.googleapis.com/youtube/v3/videos?part=snippet&id='
                . urlencode($ids) . '&key=' . urlencode($this->apiKey);

            $response = $this->http->get($apiUrl);
            if (!$response) {
                continue;
            }

            $data = json_decode($response, true);
            if (!isset($data['items'])) {
                continue;
            }

            foreach ($data['items'] as $item) {
                $results[$item['id']] = $item['snippet']['description'] ?? '';
            }
        }

        return $results;
    }

    /**
     * Extract video ID from YouTube URL
     *
     * @param string $url YouTube URL
     * @return string|null Video ID or null if not found
     */
    public function extractVideoId(string $url): ?string {
        $patterns = [
            '/youtube\.com\/watch\?.*v=([^&]+)/',
            '/youtube\.com\/embed\/([^\/\?]+)/',
            '/youtube\.com\/shorts\/([^\/\?]+)/',
            '/youtu\.be\/([^\/\?]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * Check if URL is a YouTube feed
     *
     * @param string $url Feed URL
     * @return bool True if YouTube feed
     */
    public function isYouTubeFeed(string $url): bool {
        return str_contains($url, 'youtube.com/feeds/');
    }

    /**
     * Add YouTube icon prefix to title
     *
     * @param string $title Original title
     * @return string Title with YouTube icon prefix
     */
    public function addIconToTitle(string $title): string {
        // Don't add if already has the icon
        if (str_starts_with($title, self::$youtubeIcon)) {
            return $title;
        }

        return self::$youtubeIcon . ' ' . $title;
    }

    /**
     * Get the YouTube icon character
     *
     * @return string UTF-8 encoded Font Awesome YouTube icon
     */
    public static function getIcon(): string {
        return self::$youtubeIcon;
    }
}
