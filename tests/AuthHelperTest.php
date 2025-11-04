<?php

use PHPUnit\Framework\TestCase;
use Gheop\Reader\AuthHelper;

/**
 * Comprehensive tests for AuthHelper
 */
class AuthHelperTest extends TestCase
{
    public function testParseSessionCookieValid(): void
    {
        $cookie = 'username|hashedpassword';
        $result = AuthHelper::parseSessionCookie($cookie);

        $this->assertIsArray($result);
        $this->assertEquals('username', $result['pseudo']);
        $this->assertEquals('hashedpassword', $result['pwd']);
    }

    public function testParseSessionCookieInvalidFormat(): void
    {
        $cookie = 'username-without-pipe';
        $result = AuthHelper::parseSessionCookie($cookie);

        $this->assertNull($result);
    }

    public function testParseSessionCookieTooManyParts(): void
    {
        $cookie = 'username|password|extra';
        $result = AuthHelper::parseSessionCookie($cookie);

        $this->assertNull($result);
    }

    public function testParseSessionCookieEmpty(): void
    {
        $cookie = '|';
        $result = AuthHelper::parseSessionCookie($cookie);

        $this->assertIsArray($result);
        $this->assertEquals('', $result['pseudo']);
        $this->assertEquals('', $result['pwd']);
    }

    public function testValidateCredentialsValid(): void
    {
        $userData = ['pwd' => 'hashedpassword123'];
        $provided = 'hashedpassword123';

        $result = AuthHelper::validateCredentials($userData, $provided);
        $this->assertTrue($result);
    }

    public function testValidateCredentialsInvalid(): void
    {
        $userData = ['pwd' => 'hashedpassword123'];
        $provided = 'wrongpassword';

        $result = AuthHelper::validateCredentials($userData, $provided);
        $this->assertFalse($result);
    }

    public function testValidateCredentialsMissingPwd(): void
    {
        $userData = ['username' => 'test'];
        $provided = 'password';

        $result = AuthHelper::validateCredentials($userData, $provided);
        $this->assertFalse($result);
    }

    public function testIsAuthenticatedTrue(): void
    {
        $session = ['pseudo' => 'testuser'];
        $result = AuthHelper::isAuthenticated($session);

        $this->assertTrue($result);
    }

    public function testIsAuthenticatedFalse(): void
    {
        $session = [];
        $result = AuthHelper::isAuthenticated($session);

        $this->assertFalse($result);
    }

    public function testIsAuthenticatedEmptyPseudo(): void
    {
        $session = ['pseudo' => ''];
        $result = AuthHelper::isAuthenticated($session);

        $this->assertFalse($result);
    }

    public function testFormatSessionCookie(): void
    {
        $result = AuthHelper::formatSessionCookie('myuser', 'mypasshash');
        $this->assertEquals('myuser|mypasshash', $result);
    }

    public function testFormatSessionCookieSpecialChars(): void
    {
        $result = AuthHelper::formatSessionCookie('user@email', 'pass|word');
        $this->assertEquals('user@email|pass|word', $result);
    }

    public function testGetCookieExpirationDefault(): void
    {
        $before = time();
        $expiration = AuthHelper::getCookieExpiration();
        $after = time();

        $this->assertGreaterThanOrEqual($before + 26000000, $expiration);
        $this->assertLessThanOrEqual($after + 26000000, $expiration);
    }

    public function testGetCookieExpirationCustom(): void
    {
        $before = time();
        $expiration = AuthHelper::getCookieExpiration(3600);
        $after = time();

        $this->assertGreaterThanOrEqual($before + 3600, $expiration);
        $this->assertLessThanOrEqual($after + 3600, $expiration);
    }

    public function testIsLogoutRequestTrue(): void
    {
        $params = ['a' => 'destroy'];
        $result = AuthHelper::isLogoutRequest($params);

        $this->assertTrue($result);
    }

    public function testIsLogoutRequestFalse(): void
    {
        $params = ['a' => 'something'];
        $result = AuthHelper::isLogoutRequest($params);

        $this->assertFalse($result);
    }

    public function testIsLogoutRequestMissing(): void
    {
        $params = [];
        $result = AuthHelper::isLogoutRequest($params);

        $this->assertFalse($result);
    }

    public function testGetRegistrationUrl(): void
    {
        $url = AuthHelper::getRegistrationUrl('reader.gheop.com');

        $this->assertStringContainsString('//gheop.com/register/', $url);
        $this->assertStringContainsString('page=reader.gheop.com', $url);
    }

    public function testGetRegistrationUrlCustomBase(): void
    {
        $url = AuthHelper::getRegistrationUrl('mysite.com', '//custom.com/signup/');

        $this->assertStringContainsString('//custom.com/signup/', $url);
        $this->assertStringContainsString('page=mysite.com', $url);
    }

    public function testGetRegistrationUrlEncoding(): void
    {
        $url = AuthHelper::getRegistrationUrl('site.com/path?param=value');

        $this->assertStringContainsString(urlencode('site.com/path?param=value'), $url);
    }

    public function testGetLoginUrl(): void
    {
        $url = AuthHelper::getLoginUrl('reader.gheop.com');

        $this->assertStringContainsString('//www.gheop.com/register/ident.php', $url);
        $this->assertStringContainsString('page=reader.gheop.com', $url);
    }

    public function testGetLoginUrlCustomBase(): void
    {
        $url = AuthHelper::getLoginUrl('mysite.com', '//custom.com/login.php');

        $this->assertStringContainsString('//custom.com/login.php', $url);
        $this->assertStringContainsString('page=mysite.com', $url);
    }

    public function testGetLoginUrlEncoding(): void
    {
        $url = AuthHelper::getLoginUrl('site.com/path?param=value');

        $this->assertStringContainsString(urlencode('site.com/path?param=value'), $url);
    }
}
