<?php

use PHPUnit\Framework\TestCase;
use Gheop\Reader\FeedUpdater;

/**
 * Comprehensive tests for FeedUpdater
 */
class FeedUpdaterTest extends TestCase
{
    public function testCompleteLinkAbsoluteUrl(): void
    {
        $link = 'https://example.com/article';
        $result = FeedUpdater::completeLink($link, 'https://master.com');

        $this->assertEquals('https://example.com/article', $result);
    }

    public function testCompleteLinkRelativeWithSlash(): void
    {
        $link = '/article/123';
        $result = FeedUpdater::completeLink($link, 'https://example.com');

        $this->assertEquals('https://example.com/article/123', $result);
    }

    public function testCompleteLinkProtocolRelative(): void
    {
        $link = '//cdn.example.com/image.jpg';
        $result = FeedUpdater::completeLink($link, 'https://example.com');

        $this->assertEquals('https://cdn.example.com/image.jpg', $result);
    }

    public function testCompleteLinkRelativeWithoutSlash(): void
    {
        $link = 'article/123';
        $result = FeedUpdater::completeLink($link, 'https://example.com');

        $this->assertEquals('https://example.com/article/123', $result);
    }

    public function testCompleteLinkNullLink(): void
    {
        $result = FeedUpdater::completeLink(null, 'https://example.com');
        $this->assertNull($result);
    }

    public function testCompleteLinkNoSchemeInMaster(): void
    {
        $link = '/article';
        $result = FeedUpdater::completeLink($link, 'example.com');

        $this->assertEquals('https://example.com/article', $result);
    }

    public function testCleanArticleLink(): void
    {
        $link = 'https://example.com/article(test)"quoted\\slash';
        $result = FeedUpdater::cleanArticleLink($link);

        $this->assertStringNotContainsString('(', $result);
        $this->assertStringNotContainsString(')', $result);
        $this->assertStringNotContainsString('"', $result);
        $this->assertEquals('https://example.com/articletestquoted\\\\slash', $result);
    }

    public function testGetLinkWithoutProtocol(): void
    {
        $https = FeedUpdater::getLinkWithoutProtocol('https://example.com/page');
        $this->assertEquals('://example.com/page', $https);

        $http = FeedUpdater::getLinkWithoutProtocol('http://example.com/page');
        $this->assertEquals('://example.com/page', $http);
    }

    public function testParseFeedDateValid(): void
    {
        $timestamp = FeedUpdater::parseFeedDate('2025-01-01 12:00:00');
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(0, $timestamp);
    }

    public function testParseFeedDateInvalid(): void
    {
        $timestamp = FeedUpdater::parseFeedDate('invalid date');
        $this->assertIsInt($timestamp);
        $this->assertLessThanOrEqual(time(), $timestamp);
    }

    public function testParseFeedDateNull(): void
    {
        $timestamp = FeedUpdater::parseFeedDate(null);
        $this->assertIsInt($timestamp);
        $this->assertLessThanOrEqual(time(), $timestamp);
    }

    public function testParseFeedDateFuture(): void
    {
        $futureDate = date('Y-m-d H:i:s', time() + 86400);
        $timestamp = FeedUpdater::parseFeedDate($futureDate);

        // Should return current time for future dates
        $this->assertLessThanOrEqual(time(), $timestamp);
    }

    public function testDetectFeedTypeRssChannel(): void
    {
        $xml = '<rss><channel><item><title>Test</title></item></channel></rss>';
        $rss = simplexml_load_string($xml);

        $type = FeedUpdater::detectFeedType($rss);
        $this->assertEquals('rss', $type);
    }

    public function testDetectFeedTypeRssDirect(): void
    {
        $xml = '<rss><item><title>Test</title></item></rss>';
        $rss = simplexml_load_string($xml);

        $type = FeedUpdater::detectFeedType($rss);
        $this->assertEquals('rss', $type);
    }

    public function testDetectFeedTypeAtom(): void
    {
        $xml = '<feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Test</title></entry></feed>';
        $rss = simplexml_load_string($xml);

        $type = FeedUpdater::detectFeedType($rss);
        $this->assertEquals('atom', $type);
    }

    public function testDetectFeedTypeUnknown(): void
    {
        $xml = '<unknown><data>Test</data></unknown>';
        $rss = simplexml_load_string($xml);

        $type = FeedUpdater::detectFeedType($rss);
        $this->assertEquals('unknown', $type);
    }

    public function testExtractFeedItemsRss(): void
    {
        $xml = '<rss><channel><item><title>Item 1</title></item><item><title>Item 2</title></item></channel></rss>';
        $rss = simplexml_load_string($xml);

        $items = FeedUpdater::extractFeedItems($rss);
        $this->assertIsArray($items);
        $this->assertCount(2, $items);
    }

    public function testExtractFeedItemsAtom(): void
    {
        $xml = '<feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Entry 1</title></entry></feed>';
        $rss = simplexml_load_string($xml);

        $items = FeedUpdater::extractFeedItems($rss);
        $this->assertIsArray($items);
        $this->assertCount(1, $items);
    }

    public function testExtractFeedItemsNone(): void
    {
        $xml = '<unknown></unknown>';
        $rss = simplexml_load_string($xml);

        $items = FeedUpdater::extractFeedItems($rss);
        $this->assertNull($items);
    }

    public function testExtractItemLinkDirect(): void
    {
        $xml = '<item><link>https://example.com/article</link></item>';
        $item = simplexml_load_string($xml);

        $link = FeedUpdater::extractItemLink($item);
        $this->assertEquals('https://example.com/article', $link);
    }

    public function testExtractItemLinkFromGuid(): void
    {
        $xml = '<item><guid>https://example.com/article</guid></item>';
        $item = simplexml_load_string($xml);

        $link = FeedUpdater::extractItemLink($item);
        $this->assertEquals('https://example.com/article', $link);
    }

    public function testExtractItemContentDescription(): void
    {
        $xml = '<item><description>Article content</description></item>';
        $item = simplexml_load_string($xml);

        $content = FeedUpdater::extractItemContent($item);
        $this->assertEquals('Article content', $content);
    }

    public function testExtractItemContentSummary(): void
    {
        $xml = '<entry><summary>Entry summary</summary></entry>';
        $item = simplexml_load_string($xml);

        $content = FeedUpdater::extractItemContent($item);
        $this->assertEquals('Entry summary', $content);
    }

    public function testExtractItemContentYouTube(): void
    {
        $xml = '<item></item>';
        $item = simplexml_load_string($xml);
        $link = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

        $content = FeedUpdater::extractItemContent($item, $link);
        $this->assertStringContainsString('<yt>dQw4w9WgXcQ</yt>', $content);
    }

    public function testExtractItemContentYouTubeShorts(): void
    {
        $xml = '<item></item>';
        $item = simplexml_load_string($xml);
        $link = 'https://www.youtube.com/shorts/abcd1234';

        $content = FeedUpdater::extractItemContent($item, $link);
        $this->assertStringContainsString('<yt>abcd1234</yt>', $content);
    }

    public function testExtractItemAuthorName(): void
    {
        $xml = '<item><author><name>John Doe</name></author></item>';
        $item = simplexml_load_string($xml);

        $author = FeedUpdater::extractItemAuthor($item);
        $this->assertEquals('John Doe', $author);
    }

    public function testExtractItemAuthorDirect(): void
    {
        $xml = '<item><author>Jane Doe</author></item>';
        $item = simplexml_load_string($xml);

        $author = FeedUpdater::extractItemAuthor($item);
        $this->assertEquals('Jane Doe', $author);
    }

    public function testExtractItemAuthorNone(): void
    {
        $xml = '<item></item>';
        $item = simplexml_load_string($xml);

        $author = FeedUpdater::extractItemAuthor($item);
        $this->assertEquals('', $author);
    }

    public function testExtractItemTitle(): void
    {
        $xml = '<item><title>Article Title</title></item>';
        $item = simplexml_load_string($xml);

        $title = FeedUpdater::extractItemTitle($item);
        $this->assertEquals('Article Title', $title);
    }

    public function testExtractItemTitleNone(): void
    {
        $xml = '<item></item>';
        $item = simplexml_load_string($xml);

        $title = FeedUpdater::extractItemTitle($item);
        $this->assertNull($title);
    }

    public function testExtractFeedTitle(): void
    {
        $xml = '<rss><channel><title>Feed Title</title></channel></rss>';
        $rss = simplexml_load_string($xml);

        $title = FeedUpdater::extractFeedTitle($rss);
        $this->assertEquals('Feed Title', $title);
    }

    public function testExtractFeedTitleDirect(): void
    {
        $xml = '<feed><title>Direct Title</title></feed>';
        $rss = simplexml_load_string($xml);

        $title = FeedUpdater::extractFeedTitle($rss);
        $this->assertEquals('Direct Title', $title);
    }

    public function testExtractMasterLink(): void
    {
        $xml = '<rss><channel><link>https://example.com</link></channel></rss>';
        $rss = simplexml_load_string($xml);

        $link = FeedUpdater::extractMasterLink($rss);
        $this->assertEquals('https://example.com', $link);
    }

    public function testCleanXml(): void
    {
        $xml = '  <rss></rss>  trailing content';
        $cleaned = FeedUpdater::cleanXml($xml);

        $this->assertEquals('<rss></rss>', $cleaned);
    }

    public function testCleanXmlImageUrls(): void
    {
        $xml = '<rss><image url="https://example.com/image.jpg?size=large" /></rss>';
        $cleaned = FeedUpdater::cleanXml($xml);

        $this->assertStringContainsString('image.jpg', $cleaned);
        $this->assertStringNotContainsString('?size=large', $cleaned);
    }

    public function testCleanXmlEmptyType(): void
    {
        $xml = '<rss><item type=""></item></rss>';
        $cleaned = FeedUpdater::cleanXml($xml);

        $this->assertStringNotContainsString('type=""', $cleaned);
    }

    public function testGetRedirectedUrl(): void
    {
        $redirected = FeedUpdater::getRedirectedUrl(
            'http://example.com/feed',
            'https://example.com/feed'
        );

        $this->assertEquals('https://example.com/feed', $redirected);
    }

    public function testGetRedirectedUrlNoRedirect(): void
    {
        $redirected = FeedUpdater::getRedirectedUrl(
            'https://example.com/feed',
            'https://example.com/feed'
        );

        $this->assertNull($redirected);
    }

    public function testIsValidItemLinkValid(): void
    {
        $this->assertTrue(FeedUpdater::isValidItemLink('https://example.com'));
    }

    public function testIsValidItemLinkInvalid(): void
    {
        $this->assertFalse(FeedUpdater::isValidItemLink(null));
        $this->assertFalse(FeedUpdater::isValidItemLink(''));
    }

    public function testCompleteLinkEdgeCases(): void
    {
        // Empty link
        $this->assertNull(FeedUpdater::completeLink('', 'https://example.com'));

        // Link with only spaces
        $link = '   ';
        $result = FeedUpdater::completeLink($link, 'https://example.com');
        $this->assertStringContainsString('example.com', $result);
    }

    public function testParseFeedDateEdgeCases(): void
    {
        // Empty string
        $timestamp = FeedUpdater::parseFeedDate('');
        $this->assertIsInt($timestamp);

        // Timestamp in the far future
        $futureDate = '2099-12-31 23:59:59';
        $timestamp = FeedUpdater::parseFeedDate($futureDate);
        $this->assertLessThanOrEqual(time(), $timestamp);

        // Various date formats
        $dates = [
            'Mon, 01 Jan 2025 12:00:00 GMT',
            '2025-01-01T12:00:00Z',
            '01 Jan 2025 12:00:00 +0000',
        ];

        foreach ($dates as $date) {
            $timestamp = FeedUpdater::parseFeedDate($date);
            $this->assertIsInt($timestamp);
            $this->assertGreaterThan(0, $timestamp);
        }
    }

    public function testExtractItemContentEdgeCases(): void
    {
        // Item with all content fields
        $xml = '<item>
            <description>Description text</description>
            <content>Content text</content>
            <summary>Summary text</summary>
        </item>';
        $item = simplexml_load_string($xml);

        $content = FeedUpdater::extractItemContent($item);
        // Should prefer description
        $this->assertEquals('Description text', $content);
    }

    public function testExtractItemContentImage(): void
    {
        $xml = '<item></item>';
        $item = simplexml_load_string($xml);
        $link = '//cdn.example.com/image.jpg';

        $content = FeedUpdater::extractItemContent($item, $link);
        $this->assertStringContainsString('<img src="//cdn.example.com/image.jpg"', $content);
    }

    public function testCleanXmlMultipleOperations(): void
    {
        $xml = '  <rss>
            <image url="https://example.com/img.png?w=100&h=100" />
            <item type="">
                <enclosure url="https://example.com/file.jpg?size=large" />
            </item>
        </rss>  trailing';

        $cleaned = FeedUpdater::cleanXml($xml);

        $this->assertStringNotContainsString('trailing', $cleaned);
        $this->assertStringNotContainsString('type=""', $cleaned);
        $this->assertStringNotContainsString('?w=100', $cleaned);
        $this->assertStringNotContainsString('?size=large', $cleaned);
    }

    public function testExtractFeedTitleEdgeCases(): void
    {
        // Empty feed
        $xml = '<rss><channel></channel></rss>';
        $rss = simplexml_load_string($xml);

        $title = FeedUpdater::extractFeedTitle($rss);
        $this->assertNull($title);
    }

    public function testExtractMasterLinkEdgeCases(): void
    {
        // No link
        $xml = '<rss><channel></channel></rss>';
        $rss = simplexml_load_string($xml);

        $link = FeedUpdater::extractMasterLink($rss);
        $this->assertNull($link);
    }

    public function testCompleteLinkWithPort(): void
    {
        $link = '/feed';
        $result = FeedUpdater::completeLink($link, 'https://example.com:8080');

        $this->assertStringContainsString('example.com', $result);
        $this->assertStringContainsString('/feed', $result);
    }

    public function testCleanArticleLinkAll(): void
    {
        $link = 'https://example.com/article(test)"quote\'single\\slash';
        $result = FeedUpdater::cleanArticleLink($link);

        $this->assertStringNotContainsString('(', $result);
        $this->assertStringNotContainsString(')', $result);
        $this->assertStringNotContainsString('"', $result);
        $this->assertEquals('https://example.com/articletestquote\'single\\\\slash', $result);
    }
}
