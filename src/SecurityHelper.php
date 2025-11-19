<?php

namespace Gheop\Reader;

/**
 * Security and sanitization utilities
 */
class SecurityHelper
{
    /**
     * Sanitize user input for SQL (basic escaping)
     *
     * @param string $input
     * @return string
     */
    public static function escapeSql(string $input): string
    {
        return addslashes($input);
    }

    /**
     * Validate user ID
     *
     * @param mixed $userId
     * @return bool
     */
    public static function isValidUserId($userId): bool
    {
        return is_numeric($userId) && $userId > 0;
    }

    /**
     * Sanitize HTML for safe display
     *
     * @param string $html
     * @param array $allowedTags
     * @return string
     */
    public static function sanitizeHtml(string $html, array $allowedTags = []): string
    {
        if (empty($allowedTags)) {
            return strip_tags($html);
        }

        return strip_tags($html, $allowedTags);
    }

    /**
     * Generate secure token
     *
     * @param int $length
     * @return string
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Validate email format
     *
     * @param string $email
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Clean string for filename
     *
     * @param string $filename
     * @return string
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove directory traversal
        $filename = basename($filename);

        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        return $filename;
    }

    /**
     * Validate date format
     *
     * @param string $date
     * @param string $format
     * @return bool
     */
    public static function isValidDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Check if string contains SQL injection patterns
     *
     * @param string $input
     * @return bool
     */
    public static function containsSqlInjection(string $input): bool
    {
        $patterns = [
            '/union.*select/i',
            '/insert.*into/i',
            '/delete.*from/i',
            '/drop.*table/i',
            '/;\s*drop/i',
            '/--/i',
            '/\/\*/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize integer input
     *
     * @param mixed $value
     * @param int $default
     * @return int
     */
    public static function sanitizeInt($value, int $default = 0): int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }

        return $default;
    }

    /**
     * Validate and sanitize URL for redirect
     *
     * @param string $url
     * @param string $defaultUrl
     * @return string
     */
    public static function sanitizeRedirectUrl(string $url, string $defaultUrl = '/'): string
    {
        // Block absolute URLs to prevent open redirect
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $defaultUrl;
        }

        // Block protocol-relative URLs
        if (strpos($url, '//') === 0) {
            return $defaultUrl;
        }

        // Block javascript: and data: URLs
        if (preg_match('/^(javascript|data):/i', $url)) {
            return $defaultUrl;
        }

        return $url;
    }
}
