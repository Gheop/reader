<?php

namespace Gheop\Reader;

/**
 * Helper for authentication and session management
 */
class AuthHelper
{
    /**
     * Parse session cookie
     *
     * @param string $cookieValue Cookie value
     * @return array|null Array with ['pseudo' => string, 'pwd' => string] or null
     */
    public static function parseSessionCookie(string $cookieValue): ?array
    {
        $parts = explode("|", $cookieValue);

        if (count($parts) !== 2) {
            return null;
        }

        return [
            'pseudo' => $parts[0],
            'pwd' => $parts[1]
        ];
    }

    /**
     * Validate user credentials
     *
     * @param array $userData User data from database
     * @param string $providedPassword Password from cookie
     * @return bool True if valid, false otherwise
     */
    public static function validateCredentials(array $userData, string $providedPassword): bool
    {
        if (!isset($userData['pwd'])) {
            return false;
        }

        return $userData['pwd'] === $providedPassword;
    }

    /**
     * Check if user is authenticated
     *
     * @param array $session Session data
     * @return bool True if authenticated, false otherwise
     */
    public static function isAuthenticated(array $session): bool
    {
        return isset($session['pseudo']) && !empty($session['pseudo']);
    }

    /**
     * Format session cookie value
     *
     * @param string $pseudo User pseudo
     * @param string $pwd User password hash
     * @return string Formatted cookie value
     */
    public static function formatSessionCookie(string $pseudo, string $pwd): string
    {
        return "$pseudo|$pwd";
    }

    /**
     * Calculate cookie expiration time
     *
     * @param int $durationSeconds Cookie duration in seconds (default 300 days)
     * @return int Unix timestamp for expiration
     */
    public static function getCookieExpiration(int $durationSeconds = 26000000): int
    {
        return time() + $durationSeconds;
    }

    /**
     * Check if logout action requested
     *
     * @param array $params Request parameters
     * @return bool True if logout requested, false otherwise
     */
    public static function isLogoutRequest(array $params): bool
    {
        return isset($params['a']) && $params['a'] === 'destroy';
    }

    /**
     * Build registration URL
     *
     * @param string $page Page to redirect after registration
     * @param string $baseUrl Base registration URL
     * @return string Complete registration URL
     */
    public static function getRegistrationUrl(string $page, string $baseUrl = '//gheop.com/register/'): string
    {
        return $baseUrl . '?page=' . urlencode($page);
    }

    /**
     * Build login URL
     *
     * @param string $page Page to redirect after login
     * @param string $baseUrl Base login URL
     * @return string Complete login URL
     */
    public static function getLoginUrl(string $page, string $baseUrl = '//www.gheop.com/register/ident.php'): string
    {
        return $baseUrl . '?page=' . urlencode($page);
    }
}
