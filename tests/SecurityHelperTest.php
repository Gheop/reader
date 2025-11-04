<?php

use PHPUnit\Framework\TestCase;
use Gheop\Reader\SecurityHelper;

/**
 * Tests for SecurityHelper utility class
 */
class SecurityHelperTest extends TestCase
{
    /**
     * Test SQL escaping
     */
    public function testEscapeSql(): void
    {
        $dangerous = "'; DROP TABLE users; --";
        $escaped = SecurityHelper::escapeSql($dangerous);

        $this->assertStringContainsString("\\'", $escaped);
        $this->assertNotEquals($dangerous, $escaped);
    }

    /**
     * Test valid user IDs
     */
    public function testIsValidUserId(): void
    {
        $this->assertTrue(SecurityHelper::isValidUserId(1));
        $this->assertTrue(SecurityHelper::isValidUserId(999));
        $this->assertTrue(SecurityHelper::isValidUserId('42'));

        $this->assertFalse(SecurityHelper::isValidUserId(0));
        $this->assertFalse(SecurityHelper::isValidUserId(-1));
        $this->assertFalse(SecurityHelper::isValidUserId('abc'));
        $this->assertFalse(SecurityHelper::isValidUserId(''));
        $this->assertFalse(SecurityHelper::isValidUserId(null));
    }

    /**
     * Test HTML sanitization
     */
    public function testSanitizeHtml(): void
    {
        $html = '<script>alert("XSS")</script><p>Safe content</p>';
        $cleaned = SecurityHelper::sanitizeHtml($html);

        $this->assertStringNotContainsString('<script>', $cleaned);
        $this->assertStringNotContainsString('<p>', $cleaned);
    }

    /**
     * Test HTML sanitization with allowed tags
     */
    public function testSanitizeHtmlWithAllowedTags(): void
    {
        $html = '<script>alert("XSS")</script><p>Safe <b>content</b></p>';
        $cleaned = SecurityHelper::sanitizeHtml($html, ['<p>', '<b>']);

        $this->assertStringNotContainsString('<script>', $cleaned);
        $this->assertStringContainsString('<p>', $cleaned);
        $this->assertStringContainsString('<b>', $cleaned);
    }

    /**
     * Test token generation
     */
    public function testGenerateToken(): void
    {
        $token1 = SecurityHelper::generateToken();
        $token2 = SecurityHelper::generateToken();

        $this->assertEquals(32, strlen($token1));
        $this->assertEquals(32, strlen($token2));
        $this->assertNotEquals($token1, $token2);

        // Test custom length
        $token64 = SecurityHelper::generateToken(64);
        $this->assertEquals(64, strlen($token64));
    }

    /**
     * Test email validation
     */
    public function testIsValidEmail(): void
    {
        $validEmails = [
            'user@example.com',
            'test.user@example.co.uk',
            'user+tag@example.com',
        ];

        foreach ($validEmails as $email) {
            $this->assertTrue(
                SecurityHelper::isValidEmail($email),
                "Should validate: $email"
            );
        }

        $invalidEmails = [
            'not-an-email',
            '@example.com',
            'user@',
            '',
        ];

        foreach ($invalidEmails as $email) {
            $this->assertFalse(
                SecurityHelper::isValidEmail($email),
                "Should not validate: $email"
            );
        }
    }

    /**
     * Test filename sanitization
     */
    public function testSanitizeFilename(): void
    {
        $this->assertEquals(
            'test.txt',
            SecurityHelper::sanitizeFilename('test.txt')
        );

        $this->assertEquals(
            'file_name.pdf',
            SecurityHelper::sanitizeFilename('file name.pdf')
        );

        // Directory traversal protection
        $this->assertEquals(
            'file.txt',
            SecurityHelper::sanitizeFilename('../../etc/passwd/../file.txt')
        );

        // Special characters removed
        $this->assertEquals(
            'test_file_123.txt',
            SecurityHelper::sanitizeFilename('test@file#123.txt')
        );
    }

    /**
     * Test date validation
     */
    public function testIsValidDate(): void
    {
        $this->assertTrue(SecurityHelper::isValidDate('2025-01-15'));
        $this->assertTrue(SecurityHelper::isValidDate('2025-12-31'));

        $this->assertFalse(SecurityHelper::isValidDate('2025-13-01'));
        $this->assertFalse(SecurityHelper::isValidDate('not-a-date'));
        $this->assertFalse(SecurityHelper::isValidDate(''));
    }

    /**
     * Test SQL injection detection
     */
    public function testContainsSqlInjection(): void
    {
        $malicious = [
            "' UNION SELECT * FROM users --",
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin'--",
            "/* comment */ SELECT",
        ];

        foreach ($malicious as $input) {
            $this->assertTrue(
                SecurityHelper::containsSqlInjection($input),
                "Should detect SQL injection in: $input"
            );
        }

        $safe = [
            "normal text",
            "user@example.com",
            "search term",
        ];

        foreach ($safe as $input) {
            $this->assertFalse(
                SecurityHelper::containsSqlInjection($input),
                "Should not flag safe input: $input"
            );
        }
    }

    /**
     * Test integer sanitization
     */
    public function testSanitizeInt(): void
    {
        $this->assertEquals(42, SecurityHelper::sanitizeInt(42));
        $this->assertEquals(42, SecurityHelper::sanitizeInt('42'));
        $this->assertEquals(0, SecurityHelper::sanitizeInt('abc'));
        $this->assertEquals(0, SecurityHelper::sanitizeInt(''));
        $this->assertEquals(100, SecurityHelper::sanitizeInt('invalid', 100));
    }

    /**
     * Test redirect URL sanitization
     */
    public function testSanitizeRedirectUrl(): void
    {
        // Safe relative URLs
        $this->assertEquals('/safe/path', SecurityHelper::sanitizeRedirectUrl('/safe/path'));
        $this->assertEquals('page.php', SecurityHelper::sanitizeRedirectUrl('page.php'));

        // Block absolute URLs
        $this->assertEquals('/', SecurityHelper::sanitizeRedirectUrl('https://evil.com'));
        $this->assertEquals('/', SecurityHelper::sanitizeRedirectUrl('http://evil.com'));

        // Block protocol-relative URLs
        $this->assertEquals('/', SecurityHelper::sanitizeRedirectUrl('//evil.com'));

        // Block javascript: URLs
        $this->assertEquals('/', SecurityHelper::sanitizeRedirectUrl('javascript:alert(1)'));

        // Block data: URLs
        $this->assertEquals('/', SecurityHelper::sanitizeRedirectUrl('data:text/html,<script>alert(1)</script>'));

        // Custom default
        $this->assertEquals(
            '/home',
            SecurityHelper::sanitizeRedirectUrl('https://evil.com', '/home')
        );
    }
}
