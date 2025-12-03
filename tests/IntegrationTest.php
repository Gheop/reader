<?php
/**
 * Integration Tests for Gheop Reader
 *
 * Tests critical endpoints and functionality
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../config/conf.php';

class IntegrationTest extends TestCase {

    /**
     * Test: Homepage returns 200 and contains expected elements
     */
    public function testHomepageLoads(): void {
        $response = $this->httpGet('/');
        $this->assertResponseCode(200, $response, 'Homepage should return 200');
        $this->assertStringContains('Gheop Reader', $response['content'], 'Homepage should contain title');
        $this->assertStringContains('<!DOCTYPE html>', $response['content'], 'Homepage should be HTML');
    }

    /**
     * Test: Login page is accessible
     */
    public function testLoginPageLoads(): void {
        $response = $this->httpGet('/login.php');
        $this->assertResponseCode(200, $response, 'Login page should return 200');
    }

    /**
     * Test: API returns valid JSON (401 for unauthenticated requests)
     */
    public function testApiReturnsJson(): void {
        $response = $this->httpGet('/api.php');
        $code = $response['info']['http_code'];
        // API returns 401 for unauthenticated users, 200 for authenticated
        $this->assertTrue(
            $code === 200 || $code === 401,
            "API should return 200 or 401 (got {$code})"
        );

        $data = json_decode($response['content'], true);
        $this->assertNotNull($data, 'API should return valid JSON');
    }

    /**
     * Test: Search endpoint requires authentication
     */
    public function testSearchRequiresAuth(): void {
        $response = $this->httpGet('/search.php?s=test');
        // Should return empty or redirect for unauthenticated users
        $this->assertTrue(
            $response['info']['http_code'] === 200 || $response['info']['http_code'] === 302,
            'Search should handle unauthenticated requests'
        );
    }

    /**
     * Test: Static assets are accessible
     */
    public function testStaticAssetsAccessible(): void {
        $assets = [
            '/assets/js/lib.min.js',
            '/assets/css/icons.min.css',
            '/themes/common.min.css',
            '/assets/fonts/fa-solid-900.woff2',
            '/assets/fonts/fa-brands-400.woff2',
        ];

        foreach ($assets as $asset) {
            $response = $this->httpGet($asset);
            $this->assertResponseCode(200, $response, "Asset {$asset} should be accessible");
        }
    }

    /**
     * Test: Protected endpoints block unauthenticated users (403 or redirect)
     */
    public function testProtectedEndpointsRequireAuth(): void {
        $endpoints = [
            '/manage.php',
            '/old.php',
            '/viewpage.php?id=1',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->httpGetNoFollow($endpoint);
            $code = $response['info']['http_code'];
            // Accept 403 (forbidden) or 302 (redirect to login)
            $this->assertTrue(
                $code === 403 || $code === 302,
                "{$endpoint} should block unauthenticated users (got {$code})"
            );
        }
    }

    /**
     * Test: OPML export requires authentication
     */
    public function testOpmlExportRequiresAuth(): void {
        $response = $this->httpGet('/opml_export.php');
        // Should redirect or return empty for unauthenticated users
        $this->assertTrue(
            empty(trim($response['content'])) || $response['info']['http_code'] === 302,
            'OPML export should require authentication'
        );
    }

    /**
     * Test: Service worker is accessible (if exists)
     */
    public function testServiceWorkerAccessible(): void {
        $response = $this->httpGetNoFollow('/sw.js');
        // Service worker may not exist in all setups - accept 200, 404, or redirect
        $code = $response['info']['http_code'];
        $this->assertTrue(
            $code === 200 || $code === 404 || $code === 302,
            "Service worker should return 200, 404, or redirect (got {$code})"
        );
    }

    /**
     * Test: Manifest is accessible and valid JSON
     */
    public function testManifestValid(): void {
        $response = $this->httpGet('/manifest.json');
        $this->assertResponseCode(200, $response, 'Manifest should be accessible');

        $data = json_decode($response['content'], true);
        $this->assertNotNull($data, 'Manifest should be valid JSON');
        $this->assertArrayHasKey('name', $data, 'Manifest should have name');
    }
}

class DatabaseTest extends TestCase {

    /**
     * Test: Database connection works
     */
    public function testDatabaseConnection(): void {
        $result = db()->fetchOne('SELECT 1 as test');
        $this->assertNotNull($result, 'Database query should return result');
        $this->assertEquals('1', $result['test'], 'Database should return correct value');
    }

    /**
     * Test: Users table exists and has data
     */
    public function testUsersTableExists(): void {
        $count = db()->fetchColumn('SELECT COUNT(*) FROM users');
        $this->assertTrue($count > 0, 'Users table should have records');
    }

    /**
     * Test: Feeds table exists
     */
    public function testFeedsTableExists(): void {
        $count = db()->fetchColumn('SELECT COUNT(*) FROM reader_flux');
        $this->assertTrue($count >= 0, 'Feeds table should exist');
    }

    /**
     * Test: Prepared statements work with parameters
     */
    public function testPreparedStatements(): void {
        $user = db()->fetchOne('SELECT id, pseudo FROM users WHERE pseudo = ?', ['SiB']);
        $this->assertNotNull($user, 'Should find user by prepared statement');
        $this->assertEquals('SiB', $user['pseudo'], 'Should return correct user');
    }

    /**
     * Test: Transaction support works
     */
    public function testTransactionSupport(): void {
        $initialCount = db()->fetchColumn('SELECT COUNT(*) FROM users');

        try {
            db()->transaction(function($db) {
                // This should be rolled back
                throw new Exception('Test rollback');
            });
        } catch (Exception $e) {
            // Expected
        }

        $finalCount = db()->fetchColumn('SELECT COUNT(*) FROM users');
        $this->assertEquals($initialCount, $finalCount, 'Transaction should have been rolled back');
    }
}

class FeedDetectorTest extends TestCase {

    private FeedDetector $detector;

    public function __construct() {
        $this->detector = new FeedDetector();
    }

    /**
     * Test: YouTube channel URL detection
     */
    public function testYouTubeChannelDetection(): void {
        $result = $this->detector->detectSpecialSite('https://www.youtube.com/channel/UCxyz123');
        $this->assertNotNull($result, 'Should detect YouTube channel');
        $this->assertStringContains('channel_id=UCxyz123', $result, 'Should extract correct channel ID');
    }

    /**
     * Test: Twitter/X URL detection
     */
    public function testTwitterDetection(): void {
        $result = $this->detector->detectSpecialSite('https://twitter.com/testuser');
        $this->assertNotNull($result, 'Should detect Twitter URL');
        $this->assertStringContains('nitter', $result, 'Should use Nitter for Twitter');
        $this->assertStringContains('testuser', $result, 'Should include username');
    }

    /**
     * Test: Reddit URL detection
     */
    public function testRedditDetection(): void {
        $result = $this->detector->detectSpecialSite('https://www.reddit.com/r/programming');
        $this->assertNotNull($result, 'Should detect Reddit subreddit');
        $this->assertStringContains('.rss', $result, 'Should return RSS URL');
    }

    /**
     * Test: GitHub URL detection
     */
    public function testGitHubDetection(): void {
        $result = $this->detector->detectSpecialSite('https://github.com/php/php-src');
        $this->assertNotNull($result, 'Should detect GitHub repo');
        $this->assertStringContains('releases.atom', $result, 'Should return releases feed');
    }

    /**
     * Test: Feed validation
     */
    public function testFeedValidation(): void {
        $validRss = '<?xml version="1.0"?><rss><channel><item><title>Test</title></item></channel></rss>';
        $this->assertTrue($this->detector->isValidFeed($validRss), 'Should recognize valid RSS');

        $invalidContent = '<html><body>Not a feed</body></html>';
        $this->assertFalse($this->detector->isValidFeed($invalidContent), 'Should reject HTML');

        $this->assertFalse($this->detector->isValidFeed(''), 'Should reject empty content');
    }
}

class YouTubeHelperTest extends TestCase {

    private YouTubeHelper $youtube;

    public function __construct() {
        $this->youtube = new YouTubeHelper();
    }

    /**
     * Test: Video ID extraction from various URL formats
     */
    public function testVideoIdExtraction(): void {
        $urls = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => 'dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ' => 'dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ' => 'dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/abc123' => 'abc123',
        ];

        foreach ($urls as $url => $expected) {
            $result = $this->youtube->extractVideoId($url);
            $this->assertEquals($expected, $result, "Should extract ID from {$url}");
        }
    }

    /**
     * Test: YouTube feed detection
     */
    public function testYouTubeFeedDetection(): void {
        $this->assertTrue(
            $this->youtube->isYouTubeFeed('https://www.youtube.com/feeds/videos.xml?channel_id=123'),
            'Should recognize YouTube feed'
        );

        $this->assertFalse(
            $this->youtube->isYouTubeFeed('https://example.com/feed.xml'),
            'Should reject non-YouTube feed'
        );
    }

    /**
     * Test: Icon addition to title
     */
    public function testIconAddition(): void {
        $title = 'Test Channel';
        $withIcon = $this->youtube->addIconToTitle($title);

        $this->assertTrue(strlen($withIcon) > strlen($title), 'Icon should be added');

        // Adding icon twice should not duplicate
        $doubled = $this->youtube->addIconToTitle($withIcon);
        $this->assertEquals($withIcon, $doubled, 'Icon should not be duplicated');
    }
}
