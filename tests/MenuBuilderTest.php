<?php

use PHPUnit\Framework\TestCase;
use Gheop\Reader\MenuBuilder;

/**
 * Comprehensive tests for MenuBuilder
 */
class MenuBuilderTest extends TestCase
{
    private array $sampleFeeds;

    protected function setUp(): void
    {
        $this->sampleFeeds = [
            '1' => ['t' => 'Tech Blog', 'n' => 5, 'd' => 'Technology news', 'l' => 'https://tech.com'],
            '2' => ['t' => 'News Site', 'n' => 10, 'd' => 'Daily news', 'l' => 'https://news.com'],
            '3' => ['t' => 'Empty Feed', 'n' => 0, 'd' => 'No updates', 'l' => 'https://empty.com'],
        ];
    }

    public function testBuildMenuJsonEmpty(): void
    {
        $json = MenuBuilder::buildMenuJson([]);
        $this->assertEquals('{}', $json);
    }

    public function testBuildMenuJsonSingle(): void
    {
        $feedData = ['"1":{"t":"Test","n":5,"d":"Desc","l":"https://test.com"}'];
        $json = MenuBuilder::buildMenuJson($feedData);

        $this->assertStringStartsWith('{', $json);
        $this->assertStringEndsWith('}', $json);
        $this->assertStringContainsString('"1":', $json);
        $this->assertStringContainsString('Test', $json);
    }

    public function testBuildMenuJsonMultiple(): void
    {
        $feedData = [
            '"1":{"t":"Feed1","n":5}',
            '"2":{"t":"Feed2","n":3}',
        ];
        $json = MenuBuilder::buildMenuJson($feedData);

        $this->assertStringContainsString('"1":', $json);
        $this->assertStringContainsString('"2":', $json);
        $this->assertStringContainsString(',', $json); // Should have comma separator
    }

    public function testParseMenuJsonValid(): void
    {
        $json = '{"1":{"t":"Test","n":5},"2":{"t":"Other","n":3}}';
        $parsed = MenuBuilder::parseMenuJson($json);

        $this->assertIsArray($parsed);
        $this->assertArrayHasKey('1', $parsed);
        $this->assertArrayHasKey('2', $parsed);
        $this->assertEquals('Test', $parsed['1']['t']);
        $this->assertEquals(5, $parsed['1']['n']);
    }

    public function testParseMenuJsonInvalid(): void
    {
        $json = '{invalid json}';
        $parsed = MenuBuilder::parseMenuJson($json);

        $this->assertNull($parsed);
    }

    public function testParseMenuJsonEmpty(): void
    {
        $json = '{}';
        $parsed = MenuBuilder::parseMenuJson($json);

        $this->assertIsArray($parsed);
        $this->assertEmpty($parsed);
    }

    public function testCountUnreadItems(): void
    {
        $count = MenuBuilder::countUnreadItems($this->sampleFeeds);
        $this->assertEquals(15, $count); // 5 + 10 + 0
    }

    public function testCountUnreadItemsEmpty(): void
    {
        $count = MenuBuilder::countUnreadItems([]);
        $this->assertEquals(0, $count);
    }

    public function testCountUnreadItemsMissingN(): void
    {
        $feeds = [
            '1' => ['t' => 'Test'], // Missing 'n'
            '2' => ['t' => 'Test2', 'n' => 5],
        ];
        $count = MenuBuilder::countUnreadItems($feeds);
        $this->assertEquals(5, $count);
    }

    public function testGetUnreadFeeds(): void
    {
        $unread = MenuBuilder::getUnreadFeeds($this->sampleFeeds);

        $this->assertCount(2, $unread);
        $this->assertArrayHasKey('1', $unread);
        $this->assertArrayHasKey('2', $unread);
        $this->assertArrayNotHasKey('3', $unread); // Has 0 unread
    }

    public function testGetUnreadFeedsEmpty(): void
    {
        $feeds = [
            '1' => ['t' => 'Feed1', 'n' => 0],
            '2' => ['t' => 'Feed2', 'n' => 0],
        ];
        $unread = MenuBuilder::getUnreadFeeds($feeds);

        $this->assertEmpty($unread);
    }

    public function testSortFeedsByTitle(): void
    {
        $feeds = [
            '1' => ['t' => 'Zebra Blog'],
            '2' => ['t' => 'Apple News'],
            '3' => ['t' => 'Middle Site'],
        ];

        $sorted = MenuBuilder::sortFeedsByTitle($feeds);
        $values = array_values($sorted);

        $this->assertEquals('Apple News', $values[0]['t']);
        $this->assertEquals('Middle Site', $values[1]['t']);
        $this->assertEquals('Zebra Blog', $values[2]['t']);
    }

    public function testSortFeedsByCaseInsensitive(): void
    {
        $feeds = [
            '1' => ['t' => 'zebra'],
            '2' => ['t' => 'APPLE'],
        ];

        $sorted = MenuBuilder::sortFeedsByTitle($feeds);
        $values = array_values($sorted);

        $this->assertEquals('APPLE', $values[0]['t']);
    }

    public function testSortFeedsByUnread(): void
    {
        $sorted = MenuBuilder::sortFeedsByUnread($this->sampleFeeds);
        $values = array_values($sorted);

        $this->assertEquals(10, $values[0]['n']); // News Site first
        $this->assertEquals(5, $values[1]['n']);  // Tech Blog second
        $this->assertEquals(0, $values[2]['n']);  // Empty Feed last
    }

    public function testSortFeedsByUnreadMissing(): void
    {
        $feeds = [
            '1' => ['t' => 'Test'],      // Missing 'n', treated as 0
            '2' => ['t' => 'Test2', 'n' => 5],
        ];

        $sorted = MenuBuilder::sortFeedsByUnread($feeds);
        $values = array_values($sorted);

        $this->assertEquals(5, $values[0]['n']);
    }

    public function testGetFeedById(): void
    {
        $feed = MenuBuilder::getFeedById($this->sampleFeeds, 1);

        $this->assertNotNull($feed);
        $this->assertEquals('Tech Blog', $feed['t']);
        $this->assertEquals(5, $feed['n']);
    }

    public function testGetFeedByIdNotFound(): void
    {
        $feed = MenuBuilder::getFeedById($this->sampleFeeds, 999);
        $this->assertNull($feed);
    }

    public function testIsValidFeedData(): void
    {
        $valid = ['t' => 'Title', 'n' => 5];
        $this->assertTrue(MenuBuilder::isValidFeedData($valid));
    }

    public function testIsValidFeedDataMissingTitle(): void
    {
        $invalid = ['n' => 5];
        $this->assertFalse(MenuBuilder::isValidFeedData($invalid));
    }

    public function testIsValidFeedDataMissingCount(): void
    {
        $invalid = ['t' => 'Title'];
        $this->assertFalse(MenuBuilder::isValidFeedData($invalid));
    }

    public function testIsValidFeedDataWrongTypes(): void
    {
        $invalid1 = ['t' => 123, 'n' => 5]; // Title not string
        $this->assertFalse(MenuBuilder::isValidFeedData($invalid1));

        $invalid2 = ['t' => 'Title', 'n' => 'not a number'];
        $this->assertFalse(MenuBuilder::isValidFeedData($invalid2));
    }

    public function testIsValidFeedDataOptionalFields(): void
    {
        $valid = ['t' => 'Title', 'n' => 5, 'd' => 'Desc', 'l' => 'Link'];
        $this->assertTrue(MenuBuilder::isValidFeedData($valid));
    }

    public function testRoundTripJsonConversion(): void
    {
        $original = '{"1":{"t":"Test","n":5},"2":{"t":"Other","n":3}}';
        $parsed = MenuBuilder::parseMenuJson($original);

        $this->assertIsArray($parsed);
        $this->assertEquals('Test', $parsed['1']['t']);
    }

    public function testCountUnreadWithNumericStrings(): void
    {
        $feeds = [
            '1' => ['t' => 'Feed1', 'n' => '10'], // String number
            '2' => ['t' => 'Feed2', 'n' => 5],
        ];

        $count = MenuBuilder::countUnreadItems($feeds);
        $this->assertEquals(15, $count);
    }

    public function testBuildMenuJsonWithSpecialCharacters(): void
    {
        $feedData = ['\"1\":{\"t\":\"Test & <Special>\",\"n\":5}'];
        $json = MenuBuilder::buildMenuJson($feedData);

        $this->assertStringContainsString('Test & <Special>', $json);
    }

    public function testParseMenuJsonLargeData(): void
    {
        $data = [];
        for ($i = 1; $i <= 100; $i++) {
            $data["\"$i\""] = "{\"t\":\"Feed $i\",\"n\":$i}";
        }

        $json = MenuBuilder::buildMenuJson($data);
        $parsed = MenuBuilder::parseMenuJson($json);

        $this->assertIsArray($parsed);
        $this->assertCount(100, $parsed);
    }

    public function testGetUnreadFeedsWithZeros(): void
    {
        $feeds = [
            '1' => ['t' => 'Feed1', 'n' => 0],
            '2' => ['t' => 'Feed2', 'n' => 5],
            '3' => ['t' => 'Feed3', 'n' => '0'], // String zero
        ];

        $unread = MenuBuilder::getUnreadFeeds($feeds);

        $this->assertCount(1, $unread);
        $this->assertArrayHasKey('2', $unread);
    }

    public function testSortFeedsByTitleEmptyTitles(): void
    {
        $feeds = [
            '1' => ['t' => 'Zebra'],
            '2' => ['t' => ''],
            '3' => ['t' => 'Apple'],
        ];

        $sorted = MenuBuilder::sortFeedsByTitle($feeds);

        $this->assertCount(3, $sorted);
    }

    public function testSortFeedsByUnreadEqualCounts(): void
    {
        $feeds = [
            '1' => ['t' => 'Feed1', 'n' => 5],
            '2' => ['t' => 'Feed2', 'n' => 5],
            '3' => ['t' => 'Feed3', 'n' => 5],
        ];

        $sorted = MenuBuilder::sortFeedsByUnread($feeds);

        $this->assertCount(3, $sorted);
    }

    public function testGetFeedByIdStringId(): void
    {
        $feeds = [
            '1' => ['t' => 'Feed1', 'n' => 5],
            '2' => ['t' => 'Feed2', 'n' => 10],
        ];

        $feed = MenuBuilder::getFeedById($feeds, 1);

        $this->assertNotNull($feed);
        $this->assertEquals('Feed1', $feed['t']);
    }

    public function testIsValidFeedDataNullValues(): void
    {
        $invalid = ['t' => null, 'n' => 5];
        $this->assertFalse(MenuBuilder::isValidFeedData($invalid));

        $invalid2 = ['t' => 'Title', 'n' => null];
        $this->assertFalse(MenuBuilder::isValidFeedData($invalid2));
    }

    public function testCountUnreadItemsWithZeroAndPositive(): void
    {
        $feeds = [
            '1' => ['t' => 'Feed1', 'n' => 0],
            '2' => ['t' => 'Feed2', 'n' => 10],
            '3' => ['t' => 'Feed3', 'n' => 5],
        ];

        $count = MenuBuilder::countUnreadItems($feeds);
        $this->assertEquals(15, $count);
    }

    public function testSortFeedsByTitleUnicode(): void
    {
        $feeds = [
            '1' => ['t' => 'Ñandú'],
            '2' => ['t' => 'Álbum'],
            '3' => ['t' => 'Épico'],
        ];

        $sorted = MenuBuilder::sortFeedsByTitle($feeds);
        $this->assertCount(3, $sorted);
    }
}
