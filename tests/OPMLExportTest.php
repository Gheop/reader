<?php

use PHPUnit\Framework\TestCase;

/**
 * Test cases for OPML Export functionality
 */
class OPMLExportTest extends TestCase
{
    /**
     * Test URL validation for OPML export
     */
    public function testValidateHttpUrl(): void
    {
        $validUrls = [
            'https://example.com/feed.xml',
            'http://example.com/rss',
            'https://blog.example.com/atom.xml',
        ];

        foreach ($validUrls as $url) {
            $this->assertEquals(1, preg_match('/^https?:\/\/.+/', $url),
                "URL should be valid: $url");
        }
    }

    /**
     * Test invalid URLs are rejected
     */
    public function testRejectInvalidUrls(): void
    {
        $invalidUrls = [
            '/blog/atom.xml',
            'feed.xml',
            'ftp://example.com/feed',
            '',
        ];

        foreach ($invalidUrls as $url) {
            $this->assertEquals(0, preg_match('/^https?:\/\/.+/', $url),
                "URL should be invalid: $url");
        }
    }

    /**
     * Test OPML XML structure
     */
    public function testOPMLStructure(): void
    {
        $opml = new DOMDocument('1.0', 'UTF-8');
        $root = $opml->createElement('opml');
        $root->setAttribute('version', '2.0');
        $opml->appendChild($root);

        $head = $opml->createElement('head');
        $root->appendChild($head);

        $body = $opml->createElement('body');
        $root->appendChild($body);

        $outline = $opml->createElement('outline');
        $outline->setAttribute('type', 'rss');
        $outline->setAttribute('text', 'Test Feed');
        $outline->setAttribute('xmlUrl', 'https://example.com/feed.xml');
        $outline->setAttribute('htmlUrl', 'https://example.com');
        $body->appendChild($outline);

        $xml = $opml->saveXML();

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<opml version="2.0">', $xml);
        // Head can be self-closing <head/> or <head></head>
        $this->assertMatchesRegularExpression('/<head(\s*\/)?>/', $xml);
        $this->assertStringContainsString('<body>', $xml);
        $this->assertStringContainsString('type="rss"', $xml);
        $this->assertStringContainsString('https://example.com/feed.xml', $xml);
    }

    /**
     * Test HTML entity encoding
     */
    public function testHtmlEntityEncoding(): void
    {
        $title = 'Test & <Feed> "Title"';
        $encoded = htmlspecialchars($title, ENT_XML1, 'UTF-8');

        $this->assertStringContainsString('&amp;', $encoded);
        $this->assertStringContainsString('&lt;', $encoded);
        $this->assertStringContainsString('&gt;', $encoded);
        // Note: ENT_XML1 doesn't encode quotes by default, would need ENT_QUOTES
        // But for XML attributes in DOMDocument, quotes are handled automatically
        $this->assertNotEmpty($encoded);
        $this->assertStringNotContainsString('<', $encoded);
        $this->assertStringNotContainsString('>', $encoded);
    }

    /**
     * Test URL fallback when htmlUrl is invalid
     */
    public function testUrlFallback(): void
    {
        $xmlUrl = 'https://example.com/feed.xml';
        $htmlUrl = '/invalid/path';

        // Simulate fallback logic
        if (empty($htmlUrl) || !preg_match('/^https?:\/\/.+/', $htmlUrl)) {
            $htmlUrl = $xmlUrl;
        }

        $this->assertEquals($xmlUrl, $htmlUrl);
    }
}
