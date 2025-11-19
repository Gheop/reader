<?php

use PHPUnit\Framework\TestCase;
use Gheop\Reader\OPMLGenerator;

/**
 * Comprehensive tests for OPML Generator
 */
class OPMLGeneratorTest extends TestCase
{
    /**
     * Test basic OPML generation with valid feeds
     */
    public function testGenerateBasicOPML(): void
    {
        $feeds = [
            [
                'id' => 1,
                'title' => 'Test Feed',
                'description' => 'A test feed',
                'rss' => 'https://example.com/feed.xml',
                'link' => 'https://example.com',
            ],
        ];

        $xml = OPMLGenerator::generate($feeds);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<opml version="2.0">', $xml);
        $this->assertStringContainsString('Gheop Reader Feeds Export', $xml);
        $this->assertStringContainsString('Test Feed', $xml);
        $this->assertStringContainsString('https://example.com/feed.xml', $xml);
    }

    /**
     * Test OPML generation with multiple feeds
     */
    public function testGenerateMultipleFeeds(): void
    {
        $feeds = [
            [
                'title' => 'Feed 1',
                'rss' => 'https://example1.com/rss',
                'link' => 'https://example1.com',
                'description' => 'First feed',
            ],
            [
                'title' => 'Feed 2',
                'rss' => 'https://example2.com/atom',
                'link' => 'https://example2.com',
                'description' => 'Second feed',
            ],
            [
                'title' => 'Feed 3',
                'rss' => 'https://example3.com/feed',
                'link' => 'https://example3.com',
                'description' => '',
            ],
        ];

        $xml = OPMLGenerator::generate($feeds);

        $this->assertStringContainsString('Feed 1', $xml);
        $this->assertStringContainsString('Feed 2', $xml);
        $this->assertStringContainsString('Feed 3', $xml);
        $this->assertStringContainsString('example1.com', $xml);
        $this->assertStringContainsString('example2.com', $xml);
        $this->assertStringContainsString('example3.com', $xml);
    }

    /**
     * Test URL validation
     */
    public function testUrlValidation(): void
    {
        $this->assertTrue(OPMLGenerator::isValidUrl('https://example.com/feed'));
        $this->assertTrue(OPMLGenerator::isValidUrl('http://example.com/rss'));
        $this->assertTrue(OPMLGenerator::isValidUrl('https://blog.example.com:8080/atom'));

        $this->assertFalse(OPMLGenerator::isValidUrl('/relative/path'));
        $this->assertFalse(OPMLGenerator::isValidUrl('ftp://example.com'));
        $this->assertFalse(OPMLGenerator::isValidUrl(''));
        $this->assertFalse(OPMLGenerator::isValidUrl('javascript:alert(1)'));
    }

    /**
     * Test invalid URLs are skipped
     */
    public function testInvalidUrlsSkipped(): void
    {
        $feeds = [
            [
                'title' => 'Valid Feed',
                'rss' => 'https://example.com/feed.xml',
                'link' => 'https://example.com',
            ],
            [
                'title' => 'Invalid Feed',
                'rss' => '/invalid/path',
                'link' => 'https://example.com',
            ],
            [
                'title' => 'Another Valid',
                'rss' => 'http://test.com/rss',
                'link' => 'http://test.com',
            ],
        ];

        $xml = OPMLGenerator::generate($feeds);

        $this->assertStringContainsString('Valid Feed', $xml);
        $this->assertStringNotContainsString('Invalid Feed', $xml);
        $this->assertStringContainsString('Another Valid', $xml);
    }

    /**
     * Test htmlUrl fallback to xmlUrl
     */
    public function testHtmlUrlFallback(): void
    {
        $feeds = [
            [
                'title' => 'Test Feed',
                'rss' => 'https://example.com/feed.xml',
                'link' => '',
            ],
        ];

        $xml = OPMLGenerator::generate($feeds);

        // Should contain feed URL twice (as both xmlUrl and htmlUrl)
        $this->assertEquals(2, substr_count($xml, 'https://example.com/feed.xml'));
    }

    /**
     * Test special characters are properly encoded
     */
    public function testSpecialCharactersEncoded(): void
    {
        $feeds = [
            [
                'title' => 'Test & <Feed> "Title"',
                'description' => 'Description with & < > " characters',
                'rss' => 'https://example.com/feed.xml',
                'link' => 'https://example.com',
            ],
        ];

        $xml = OPMLGenerator::generate($feeds);

        $this->assertStringContainsString('&amp;', $xml);
        $this->assertStringContainsString('&lt;', $xml);
        $this->assertStringContainsString('&gt;', $xml);
        $this->assertStringNotContainsString('Test & <Feed>', $xml);
    }

    /**
     * Test empty feeds array
     */
    public function testEmptyFeedsArray(): void
    {
        $xml = OPMLGenerator::generate([]);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<opml version="2.0">', $xml);
        $this->assertStringContainsString('<head>', $xml);
        $this->assertStringContainsString('<body>', $xml);
    }

    /**
     * Test filename generation
     */
    public function testFilenameGeneration(): void
    {
        $filename = OPMLGenerator::getFilename('2025-01-15');
        $this->assertEquals('gheop-reader-feeds-2025-01-15.opml', $filename);

        $filenameToday = OPMLGenerator::getFilename();
        $this->assertStringStartsWith('gheop-reader-feeds-', $filenameToday);
        $this->assertStringEndsWith('.opml', $filenameToday);
    }

    /**
     * Test OPML structure is valid
     */
    public function testOPMLStructureValid(): void
    {
        $feeds = [
            [
                'title' => 'Test',
                'rss' => 'https://example.com/feed',
                'link' => 'https://example.com',
            ],
        ];

        $xml = OPMLGenerator::generate($feeds);

        // Load as DOMDocument to validate structure
        $doc = new DOMDocument();
        $loaded = @$doc->loadXML($xml);

        $this->assertTrue($loaded, 'Generated XML should be valid');
        $this->assertEquals('opml', $doc->documentElement->nodeName);
        $this->assertEquals('2.0', $doc->documentElement->getAttribute('version'));
    }

    /**
     * Test outline attributes
     */
    public function testOutlineAttributes(): void
    {
        $feeds = [
            [
                'title' => 'My Blog',
                'description' => 'Personal blog',
                'rss' => 'https://myblog.com/feed.xml',
                'link' => 'https://myblog.com',
            ],
        ];

        $xml = OPMLGenerator::generate($feeds);

        $this->assertStringContainsString('type="rss"', $xml);
        $this->assertStringContainsString('text="My Blog"', $xml);
        $this->assertStringContainsString('title="My Blog"', $xml);
        $this->assertStringContainsString('xmlUrl="https://myblog.com/feed.xml"', $xml);
        $this->assertStringContainsString('htmlUrl="https://myblog.com"', $xml);
        $this->assertStringContainsString('description="Personal blog"', $xml);
    }

    /**
     * Test feeds without description
     */
    public function testFeedWithoutDescription(): void
    {
        $feeds = [
            [
                'title' => 'No Description Feed',
                'rss' => 'https://example.com/feed',
                'link' => 'https://example.com',
                'description' => '',
            ],
        ];

        $xml = OPMLGenerator::generate($feeds);

        $this->assertStringContainsString('No Description Feed', $xml);
        // Should not have description attribute if empty
        $this->assertStringNotContainsString('description=""', $xml);
    }

    /**
     * Test date format in OPML head
     */
    public function testDateFormat(): void
    {
        $xml = OPMLGenerator::generate([]);

        // RFC 2822 date format
        $this->assertMatchesRegularExpression('/<dateCreated>[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4}/', $xml);
    }
}
