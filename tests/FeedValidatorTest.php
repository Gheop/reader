<?php

use PHPUnit\Framework\TestCase;
use Gheop\Reader\FeedValidator;

/**
 * Tests for FeedValidator utility class
 */
class FeedValidatorTest extends TestCase
{
    /**
     * Test valid feed URLs
     */
    public function testValidFeedUrls(): void
    {
        $validUrls = [
            'https://example.com/feed.xml',
            'http://blog.example.com/rss',
            'https://example.com:8080/atom.xml',
            'https://sub.domain.example.com/feed',
        ];

        foreach ($validUrls as $url) {
            $this->assertTrue(
                FeedValidator::isValidFeedUrl($url),
                "Should validate: $url"
            );
        }
    }

    /**
     * Test invalid feed URLs
     */
    public function testInvalidFeedUrls(): void
    {
        $invalidUrls = [
            '',
            '/relative/path',
            'ftp://example.com/feed',
            'javascript:alert(1)',
            'not-a-url',
            'http://',
            'https://',
        ];

        foreach ($invalidUrls as $url) {
            $this->assertFalse(
                FeedValidator::isValidFeedUrl($url),
                "Should not validate: $url"
            );
        }
    }

    /**
     * Test title sanitization
     */
    public function testSanitizeTitle(): void
    {
        $this->assertEquals(
            'Clean Title',
            FeedValidator::sanitizeTitle('  Clean Title  ')
        );

        $this->assertEquals(
            'Title Without Control Chars',
            FeedValidator::sanitizeTitle("Title\x00Without\x1FControl\x7FChars")
        );

        $this->assertLessThanOrEqual(
            255,
            mb_strlen(FeedValidator::sanitizeTitle(str_repeat('A', 300)))
        );
    }

    /**
     * Test feed data validation with valid data
     */
    public function testValidateFeedDataValid(): void
    {
        $feedData = [
            'title' => 'Test Feed',
            'description' => 'A test feed description',
            'rss' => 'https://example.com/feed.xml',
            'link' => 'https://example.com',
            'language' => 'en',
        ];

        $result = FeedValidator::validateFeedData($feedData);

        $this->assertNotNull($result);
        $this->assertEquals('Test Feed', $result['title']);
        $this->assertEquals('https://example.com/feed.xml', $result['rss']);
        $this->assertEquals('https://example.com', $result['link']);
    }

    /**
     * Test feed data validation with invalid RSS URL
     */
    public function testValidateFeedDataInvalidRss(): void
    {
        $feedData = [
            'title' => 'Test Feed',
            'rss' => '/invalid/url',
        ];

        $result = FeedValidator::validateFeedData($feedData);

        $this->assertNull($result);
    }

    /**
     * Test feed data validation with missing title
     */
    public function testValidateFeedDataMissingTitle(): void
    {
        $feedData = [
            'rss' => 'https://example.com/feed.xml',
        ];

        $result = FeedValidator::validateFeedData($feedData);

        $this->assertNotNull($result);
        $this->assertEquals('Untitled Feed', $result['title']);
    }

    /**
     * Test feed data validation with invalid link falls back to RSS
     */
    public function testValidateFeedDataInvalidLinkFallback(): void
    {
        $feedData = [
            'title' => 'Test',
            'rss' => 'https://example.com/feed.xml',
            'link' => '/invalid',
        ];

        $result = FeedValidator::validateFeedData($feedData);

        $this->assertNotNull($result);
        $this->assertEquals('https://example.com/feed.xml', $result['link']);
    }

    /**
     * Test feed type detection for RSS
     */
    public function testDetectFeedTypeRss(): void
    {
        $rssContent = '<?xml version="1.0"?><rss version="2.0"><channel></channel></rss>';
        $this->assertEquals('rss', FeedValidator::detectFeedType($rssContent));
    }

    /**
     * Test feed type detection for Atom
     */
    public function testDetectFeedTypeAtom(): void
    {
        $atomContent = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"></feed>';
        $this->assertEquals('atom', FeedValidator::detectFeedType($atomContent));
    }

    /**
     * Test feed type detection for unknown
     */
    public function testDetectFeedTypeUnknown(): void
    {
        $content = '<html><body>Not a feed</body></html>';
        $this->assertEquals('unknown', FeedValidator::detectFeedType($content));
    }

    /**
     * Test domain extraction
     */
    public function testExtractDomain(): void
    {
        $this->assertEquals(
            'example.com',
            FeedValidator::extractDomain('https://example.com/feed.xml')
        );

        $this->assertEquals(
            'blog.example.com',
            FeedValidator::extractDomain('https://blog.example.com:8080/rss')
        );

        $this->assertEquals(
            '',
            FeedValidator::extractDomain('invalid-url')
        );
    }

    /**
     * Test description sanitization removes HTML
     */
    public function testValidateFeedDataSanitizesDescription(): void
    {
        $feedData = [
            'title' => 'Test',
            'rss' => 'https://example.com/feed.xml',
            'description' => '<script>alert("XSS")</script>Description with <b>HTML</b>',
        ];

        $result = FeedValidator::validateFeedData($feedData);

        $this->assertStringNotContainsString('<script>', $result['description']);
        $this->assertStringNotContainsString('<b>', $result['description']);
    }

    /**
     * Test description length limit
     */
    public function testValidateFeedDataDescriptionLengthLimit(): void
    {
        $feedData = [
            'title' => 'Test',
            'rss' => 'https://example.com/feed.xml',
            'description' => str_repeat('A', 2000),
        ];

        $result = FeedValidator::validateFeedData($feedData);

        $this->assertLessThanOrEqual(1000, mb_strlen($result['description']));
    }
}
