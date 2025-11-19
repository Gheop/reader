<?php

use PHPUnit\Framework\TestCase;

/**
 * Test helper functions and utilities
 */
class HelperFunctionsTest extends TestCase
{
    /**
     * Test cutString function for text truncation
     */
    public function testCutString(): void
    {
        require_once __DIR__ . '/../clean_text.php';

        $long = 'This is a very long string that should be truncated';
        $short = cutString($long, 0, 20);

        $this->assertLessThanOrEqual(20, mb_strlen($short));
        $this->assertStringContainsString('…', $short);
    }

    /**
     * Test URL validation regex
     */
    public function testUrlValidation(): void
    {
        $validUrls = [
            'https://example.com/feed.xml',
            'http://blog.example.com/rss',
            'https://example.com:8080/feed',
        ];

        $invalidUrls = [
            '/relative/path',
            'javascript:alert(1)',
            'ftp://example.com',
            '',
        ];

        foreach ($validUrls as $url) {
            $this->assertMatchesRegularExpression(
                '/^https?:\/\/.+/',
                $url,
                "Should match valid URL: $url"
            );
        }

        foreach ($invalidUrls as $url) {
            $this->assertDoesNotMatchRegularExpression(
                '/^https?:\/\/.+/',
                $url,
                "Should not match invalid URL: $url"
            );
        }
    }

    /**
     * Test SQL escaping
     */
    public function testSqlEscaping(): void
    {
        $dangerous = "' OR '1'='1";
        $escaped = addslashes($dangerous);

        $this->assertStringContainsString("\\'", $escaped);
        $this->assertStringNotContainsString("' OR", $escaped);
    }

    /**
     * Test JSON encoding
     */
    public function testJsonEncoding(): void
    {
        $data = [
            'id' => 1,
            'title' => 'Test Feed',
            'description' => 'A test & <description>',
        ];

        $json = json_encode($data);
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals($data['id'], $decoded['id']);
        $this->assertEquals($data['title'], $decoded['title']);
    }

    /**
     * Test XML special chars encoding
     */
    public function testXmlEncoding(): void
    {
        $text = 'Test & <tag> "quotes"';
        $encoded = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString('&amp;', $encoded);
        $this->assertStringContainsString('&lt;', $encoded);
        $this->assertStringContainsString('&gt;', $encoded);
        $this->assertStringContainsString('&quot;', $encoded);
    }

    /**
     * Test date formatting
     */
    public function testDateFormatting(): void
    {
        $timestamp = strtotime('2025-01-01 12:00:00');
        $formatted = date('r', $timestamp);

        $this->assertMatchesRegularExpression('/\d{2} Jan 2025/', $formatted);
    }

    /**
     * Test array operations
     */
    public function testArrayFiltering(): void
    {
        $feeds = [
            ['id' => 1, 'active' => true],
            ['id' => 2, 'active' => false],
            ['id' => 3, 'active' => true],
        ];

        $active = array_filter($feeds, function($feed) {
            return $feed['active'];
        });

        $this->assertCount(2, $active);
    }

    /**
     * Test string manipulation
     */
    public function testStringManipulation(): void
    {
        $title = '  Test Feed Title  ';
        $cleaned = trim($title);

        $this->assertEquals('Test Feed Title', $cleaned);
        $this->assertStringStartsNotWith(' ', $cleaned);
        $this->assertStringEndsNotWith(' ', $cleaned);
    }
}
