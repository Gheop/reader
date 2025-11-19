<?php

use PHPUnit\Framework\TestCase;
use Gheop\Reader\RSSDiscovery;

/**
 * Comprehensive tests for RSSDiscovery
 */
class RSSDiscoveryTest extends TestCase
{
    public function testIsRSSContentValidRSS(): void
    {
        $xml = '<rss><channel><item><title>Test</title></item></channel></rss>';
        $this->assertTrue(RSSDiscovery::isRSSContent($xml));
    }

    public function testIsRSSContentValidAtom(): void
    {
        $xml = '<feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Test</title></entry></feed>';
        $this->assertTrue(RSSDiscovery::isRSSContent($xml));
    }

    public function testIsRSSContentValidRSSWithoutChannel(): void
    {
        $xml = '<rss><item><title>Test</title></item></rss>';
        $this->assertTrue(RSSDiscovery::isRSSContent($xml));
    }

    public function testIsRSSContentInvalidXML(): void
    {
        $xml = '<invalid>not a feed</invalid>';
        $this->assertFalse(RSSDiscovery::isRSSContent($xml));
    }

    public function testIsRSSContentEmptyString(): void
    {
        $this->assertFalse(RSSDiscovery::isRSSContent(''));
    }

    public function testIsRSSContentMalformedXML(): void
    {
        $xml = '<rss><channel><item>malformed';
        $this->assertFalse(RSSDiscovery::isRSSContent($xml));
    }

    public function testGetSpecialSiteFeedUrlYouTubeChannel(): void
    {
        $url = 'https://www.youtube.com/channel/UCxxxxxx';
        $result = RSSDiscovery::getSpecialSiteFeedUrl($url);

        $this->assertEquals('https://www.youtube.com/feeds/videos.xml?channel_id=UCxxxxxx', $result);
    }

    public function testGetSpecialSiteFeedUrlYouTubeUser(): void
    {
        $url = 'https://www.youtube.com/user/testuser';
        $result = RSSDiscovery::getSpecialSiteFeedUrl($url);

        $this->assertStringContainsString('youtube_user:testuser', $result);
    }

    public function testGetSpecialSiteFeedUrlYouTubeVideo(): void
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $result = RSSDiscovery::getSpecialSiteFeedUrl($url);

        $this->assertStringContainsString('youtube_video:dQw4w9WgXcQ', $result);
    }

    public function testGetSpecialSiteFeedUrlYouTubeShortUrl(): void
    {
        $url = 'https://youtu.be/dQw4w9WgXcQ';
        $result = RSSDiscovery::getSpecialSiteFeedUrl($url);

        $this->assertStringContainsString('dQw4w9WgXcQ', $result);
    }

    public function testGetSpecialSiteFeedUrlDailymotionShortUrl(): void
    {
        $url = 'https://dai.ly/x123456';
        $result = RSSDiscovery::getSpecialSiteFeedUrl($url);

        $this->assertStringContainsString('dailymotion', $result);
    }

    public function testGetSpecialSiteFeedUrlDailymotionVideo(): void
    {
        $url = 'https://www.dailymotion.com/video/x123456';
        $result = RSSDiscovery::getSpecialSiteFeedUrl($url);

        $this->assertStringContainsString('dailymotion_video:x123456', $result);
    }

    public function testGetSpecialSiteFeedUrlDailymotionUser(): void
    {
        $url = 'https://www.dailymotion.com/testuser';
        $result = RSSDiscovery::getSpecialSiteFeedUrl($url);

        $this->assertEquals('http://www.dailymotion.com/rss/user/testuser', $result);
    }

    public function testGetSpecialSiteFeedUrlTwitter(): void
    {
        $url = 'https://twitter.com/testuser';
        $result = RSSDiscovery::getSpecialSiteFeedUrl($url);

        $this->assertStringContainsString('scraping/twitter.com.php?f=testuser', $result);
    }

    public function testGetSpecialSiteFeedUrlTwitterWithWWW(): void
    {
        $url = 'https://www.twitter.com/testuser';
        $result = RSSDiscovery::getSpecialSiteFeedUrl($url);

        $this->assertStringContainsString('testuser', $result);
    }

    public function testGetSpecialSiteFeedUrlReddit(): void
    {
        $url = 'https://www.reddit.com/r/programming';
        $result = RSSDiscovery::getSpecialSiteFeedUrl($url);

        $this->assertEquals('https://www.reddit.com/r/programming.rss', $result);
    }

    public function testGetSpecialSiteFeedUrlMedium(): void
    {
        $url = 'https://testuser.medium.com/';
        $result = RSSDiscovery::getSpecialSiteFeedUrl($url);

        $this->assertEquals('https://testuser.medium.com/feed', $result);
    }

    public function testGetSpecialSiteFeedUrlMediumWWW(): void
    {
        $url = 'https://www.medium.com/publication';
        $result = RSSDiscovery::getSpecialSiteFeedUrl($url);

        $this->assertFalse($result);
    }

    public function testGetSpecialSiteFeedUrlRegularSite(): void
    {
        $url = 'https://example.com/blog';
        $result = RSSDiscovery::getSpecialSiteFeedUrl($url);

        $this->assertFalse($result);
    }

    public function testValidateUrlValid(): void
    {
        $url = 'https://example.com/feed.xml';
        $result = RSSDiscovery::validateUrl($url);

        $this->assertEquals('https://example.com/feed.xml', $result);
    }

    public function testValidateUrlWithoutProtocol(): void
    {
        $url = 'example.com/feed.xml';
        $result = RSSDiscovery::validateUrl($url);

        $this->assertStringStartsWith('//', $result);
        $this->assertStringContainsString('example.com', $result);
    }

    public function testValidateUrlInvalid(): void
    {
        $url = 'not a url';
        $result = RSSDiscovery::validateUrl($url);

        $this->assertFalse($result);
    }

    public function testValidateUrlEmpty(): void
    {
        $result = RSSDiscovery::validateUrl('');
        $this->assertFalse($result);
    }

    public function testValidateUrlWithSpecialChars(): void
    {
        $url = 'https://example.com/feed?param=value&other=123';
        $result = RSSDiscovery::validateUrl($url);

        $this->assertNotFalse($result);
        $this->assertStringContainsString('example.com', $result);
    }

    public function testExtractFeedMetadataRSS(): void
    {
        $xml = '<rss><channel>
            <title>Test Feed</title>
            <link>https://example.com</link>
            <description>Test Description</description>
            <language>en-US</language>
        </channel></rss>';
        $rss = new \SimpleXMLElement($xml);

        $metadata = RSSDiscovery::extractFeedMetadata($rss);

        $this->assertEquals('Test Feed', $metadata['title']);
        $this->assertEquals('https://example.com', $metadata['link']);
        $this->assertEquals('Test Description', $metadata['description']);
        $this->assertEquals('en-US', $metadata['language']);
    }

    public function testExtractFeedMetadataAtom(): void
    {
        $xml = '<feed xmlns="http://www.w3.org/2005/Atom">
            <title>Test Feed</title>
            <link href="https://example.com" />
            <subtitle>Test Subtitle</subtitle>
        </feed>';
        $rss = new \SimpleXMLElement($xml);

        $metadata = RSSDiscovery::extractFeedMetadata($rss);

        $this->assertEquals('Test Feed', $metadata['title']);
        $this->assertEquals('https://example.com', $metadata['link']);
        $this->assertEquals('Test Subtitle', $metadata['description']);
    }

    public function testExtractFeedMetadataEmpty(): void
    {
        $xml = '<rss><channel></channel></rss>';
        $rss = new \SimpleXMLElement($xml);

        $metadata = RSSDiscovery::extractFeedMetadata($rss);

        $this->assertEquals('', $metadata['title']);
        $this->assertEquals('', $metadata['link']);
        $this->assertEquals('', $metadata['description']);
        $this->assertEquals('', $metadata['language']);
    }

    public function testCompleteLinkAbsolute(): void
    {
        $link = 'https://example.com/page';
        $base = 'https://other.com';

        $result = RSSDiscovery::completeLink($link, $base);
        $this->assertEquals('https://example.com/page', $result);
    }

    public function testCompleteLinkRelativePath(): void
    {
        $link = '/page';
        $base = 'https://example.com';

        $result = RSSDiscovery::completeLink($link, $base);
        $this->assertEquals('https://example.com/page', $result);
    }

    public function testCompleteLinkRelativeWithoutSlash(): void
    {
        $link = 'page';
        $base = 'https://example.com';

        $result = RSSDiscovery::completeLink($link, $base);
        $this->assertEquals('https://example.com/page', $result);
    }

    public function testCompleteLinkNoScheme(): void
    {
        $link = '/page';
        $base = 'example.com';

        $result = RSSDiscovery::completeLink($link, $base);
        $this->assertStringStartsWith('https://', $result);
    }

    public function testParseTwitterHandleAt(): void
    {
        $result = RSSDiscovery::parseTwitterHandle('@username');

        $this->assertIsArray($result);
        $this->assertEquals('twitter', $result['type']);
        $this->assertEquals('username', $result['handle']);
    }

    public function testParseTwitterHandleHash(): void
    {
        $result = RSSDiscovery::parseTwitterHandle('#hashtag');

        $this->assertIsArray($result);
        $this->assertEquals('twitter', $result['type']);
        $this->assertEquals('hashtag', $result['handle']);
    }

    public function testParseTwitterHandleRegular(): void
    {
        $result = RSSDiscovery::parseTwitterHandle('username');
        $this->assertNull($result);
    }

    public function testParseTwitterHandleEmpty(): void
    {
        $result = RSSDiscovery::parseTwitterHandle('');
        $this->assertNull($result);
    }

    public function testIsValidFeedMetadataValid(): void
    {
        $metadata = [
            'title' => 'Test Feed',
            'link' => 'https://example.com',
            'description' => 'Test',
            'language' => 'en'
        ];

        $this->assertTrue(RSSDiscovery::isValidFeedMetadata($metadata));
    }

    public function testIsValidFeedMetadataMissingTitle(): void
    {
        $metadata = [
            'title' => '',
            'link' => 'https://example.com',
            'description' => 'Test',
            'language' => 'en'
        ];

        $this->assertFalse(RSSDiscovery::isValidFeedMetadata($metadata));
    }

    public function testIsValidFeedMetadataMissingLink(): void
    {
        $metadata = [
            'title' => 'Test',
            'link' => '',
            'description' => 'Test',
            'language' => 'en'
        ];

        $this->assertFalse(RSSDiscovery::isValidFeedMetadata($metadata));
    }

    public function testShouldPrefixTitleGitHub(): void
    {
        $url = 'https://github.com/user/repo/releases.atom';
        $this->assertTrue(RSSDiscovery::shouldPrefixTitle($url));
    }

    public function testShouldPrefixTitleYouTube(): void
    {
        $url = 'https://www.youtube.com/feeds/videos.xml?channel_id=UC123';
        $this->assertTrue(RSSDiscovery::shouldPrefixTitle($url));
    }

    public function testShouldPrefixTitleRegular(): void
    {
        $url = 'https://example.com/feed.xml';
        $this->assertFalse(RSSDiscovery::shouldPrefixTitle($url));
    }
}
