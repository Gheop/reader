<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for clean_text.php utility functions
 */
class CleanTextTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../clean_text.php';
    }

    /**
     * Test cutString with short text
     */
    public function testCutStringShortText(): void
    {
        $short = 'Hello World';
        $result = cutString($short, 0, 50);

        $this->assertEquals('Hello World', $result);
    }

    /**
     * Test cutString with long text
     */
    public function testCutStringLongText(): void
    {
        $long = 'This is a very long string that needs to be truncated at some point';
        $result = cutString($long, 0, 30);

        $this->assertLessThanOrEqual(30, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
        $this->assertStringStartsWith('This is', $result);
    }

    /**
     * Test cutString preserves word boundaries
     */
    public function testCutStringWordBoundary(): void
    {
        $text = 'The quick brown fox jumps over the lazy dog';
        $result = cutString($text, 0, 20);

        // Should not cut in middle of word
        $this->assertDoesNotMatchRegularExpression('/\w…/', $result);
    }

    /**
     * Test cutString with UTF-8 characters
     */
    public function testCutStringUTF8(): void
    {
        $text = 'Héllo wörld with spëcial çharacters';
        $result = cutString($text, 0, 15);

        $this->assertLessThanOrEqual(15, mb_strlen($result));
    }

    /**
     * Test cutString custom end string
     */
    public function testCutStringCustomEndString(): void
    {
        $text = 'This is a long text that will be truncated';
        $result = cutString($text, 0, 20, '...');

        $this->assertStringEndsWith('...', $result);
    }

    /**
     * Test clean_txt removes control characters
     */
    public function testCleanTxtRemovesControlChars(): void
    {
        $dirty = "Text with\x00null\x1Fcontrol\x7Fchars";
        $clean = clean_txt($dirty);

        $this->assertStringNotContainsString("\x00", $clean);
        $this->assertStringNotContainsString("\x1F", $clean);
        $this->assertStringNotContainsString("\x7F", $clean);
    }

    /**
     * Test clean_txt handles empty string
     */
    public function testCleanTxtEmptyString(): void
    {
        $result = clean_txt('');
        $this->assertEquals('', $result);
    }

    /**
     * Test clean_txt preserves normal text
     */
    public function testCleanTxtPreservesNormalText(): void
    {
        $text = 'Normal text with some content';
        $result = clean_txt($text);

        $this->assertStringContainsString('Normal text', $result);
    }
}
