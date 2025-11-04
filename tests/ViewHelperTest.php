<?php

use PHPUnit\Framework\TestCase;
use Gheop\Reader\ViewHelper;

/**
 * Comprehensive tests for ViewHelper - targeting 80%+ coverage
 */
class ViewHelperTest extends TestCase
{
    public function testBuildLimitClauseDefault(): void
    {
        $limit = ViewHelper::buildLimitClause(null);
        $this->assertEquals('50', $limit);
    }

    public function testBuildLimitClauseWithNumber(): void
    {
        $limit = ViewHelper::buildLimitClause(25);
        $this->assertEquals('25', $limit);
    }

    public function testBuildLimitClauseMaxLimit(): void
    {
        $limit = ViewHelper::buildLimitClause(200); // Exceeds max
        $this->assertEquals('100', $limit); // Capped at 100
    }

    public function testBuildLimitClauseWithOffset(): void
    {
        $limit = ViewHelper::buildLimitClause(25, 10);
        $this->assertEquals('10, 25', $limit);
    }

    public function testBuildLimitClauseZeroOffset(): void
    {
        $limit = ViewHelper::buildLimitClause(25, 0);
        $this->assertEquals('0, 25', $limit);
    }

    public function testBuildLimitClauseInvalidNb(): void
    {
        $limit = ViewHelper::buildLimitClause('invalid');
        $this->assertEquals('50', $limit);
    }

    public function testBuildLimitClauseNegativeNb(): void
    {
        $limit = ViewHelper::buildLimitClause(-10);
        $this->assertEquals('50', $limit);
    }

    public function testBuildFeedFilterWithId(): void
    {
        $filter = ViewHelper::buildFeedFilter(42);
        $this->assertEquals(' and F.id=42', $filter);
    }

    public function testBuildFeedFilterWithoutId(): void
    {
        $filter = ViewHelper::buildFeedFilter(null);
        $this->assertEquals('', $filter);
    }

    public function testBuildFeedFilterInvalidId(): void
    {
        $filter = ViewHelper::buildFeedFilter('invalid');
        $this->assertEquals('', $filter);

        $filter = ViewHelper::buildFeedFilter(0);
        $this->assertEquals('', $filter);

        $filter = ViewHelper::buildFeedFilter(-5);
        $this->assertEquals('', $filter);
    }

    public function testSanitizeParams(): void
    {
        $params = [
            'nb' => '10',
            'id' => '5',
            'offset' => '20',
        ];

        $sanitized = ViewHelper::sanitizeParams($params);

        $this->assertEquals(10, $sanitized['nb']);
        $this->assertEquals(5, $sanitized['id']);
        $this->assertEquals(20, $sanitized['offset']);
    }

    public function testSanitizeParamsInvalid(): void
    {
        $params = [
            'nb' => 'invalid',
            'id' => 'abc',
        ];

        $sanitized = ViewHelper::sanitizeParams($params);

        $this->assertNull($sanitized['nb']);
        $this->assertNull($sanitized['id']);
        $this->assertNull($sanitized['offset']);
    }

    public function testSanitizeParamsMissing(): void
    {
        $params = [];
        $sanitized = ViewHelper::sanitizeParams($params);

        $this->assertNull($sanitized['nb']);
        $this->assertNull($sanitized['id']);
        $this->assertNull($sanitized['offset']);
    }

    public function testParseArticlesJsonValid(): void
    {
        $json = '{"1":{"t":"Article 1","p":"2025-01-01"},"2":{"t":"Article 2"}}';
        $parsed = ViewHelper::parseArticlesJson($json);

        $this->assertIsArray($parsed);
        $this->assertCount(2, $parsed);
        $this->assertEquals('Article 1', $parsed['1']['t']);
    }

    public function testParseArticlesJsonEmpty(): void
    {
        $parsed = ViewHelper::parseArticlesJson('{}');
        $this->assertIsArray($parsed);
        $this->assertEmpty($parsed);
    }

    public function testParseArticlesJsonEmptyString(): void
    {
        $parsed = ViewHelper::parseArticlesJson('');
        $this->assertIsArray($parsed);
        $this->assertEmpty($parsed);
    }

    public function testParseArticlesJsonInvalid(): void
    {
        $parsed = ViewHelper::parseArticlesJson('{invalid json}');
        $this->assertNull($parsed);
    }

    public function testFormatArticle(): void
    {
        $input = [
            't' => 'Test Article',
            'p' => '2025-01-01',
            'd' => 'Description',
            'l' => 'https://example.com',
            'a' => 'Author',
            'f' => 5,
            'n' => 'Feed Name',
        ];

        $formatted = ViewHelper::formatArticle($input);

        $this->assertEquals('Test Article', $formatted['t']);
        $this->assertEquals('2025-01-01', $formatted['p']);
        $this->assertEquals('Description', $formatted['d']);
        $this->assertEquals('https://example.com', $formatted['l']);
        $this->assertEquals('Author', $formatted['a']);
        $this->assertEquals(5, $formatted['f']);
        $this->assertEquals(1, $formatted['r']); // Default unread
    }

    public function testFormatArticleMissingFields(): void
    {
        $input = ['t' => 'Title'];
        $formatted = ViewHelper::formatArticle($input);

        $this->assertEquals('Title', $formatted['t']);
        $this->assertEquals('', $formatted['p']);
        $this->assertEquals('', $formatted['d']);
        $this->assertEquals('', $formatted['a']);
        $this->assertEquals(0, $formatted['f']);
    }

    public function testCountArticles(): void
    {
        $articles = [
            '1' => ['t' => 'A1'],
            '2' => ['t' => 'A2'],
            '3' => ['t' => 'A3'],
        ];

        $count = ViewHelper::countArticles($articles);
        $this->assertEquals(3, $count);
    }

    public function testCountArticlesEmpty(): void
    {
        $count = ViewHelper::countArticles([]);
        $this->assertEquals(0, $count);
    }

    public function testFilterByReadStatusUnreadOnly(): void
    {
        $articles = [
            '1' => ['t' => 'A1', 'r' => 1], // Unread
            '2' => ['t' => 'A2', 'r' => 0], // Read
            '3' => ['t' => 'A3', 'r' => 1], // Unread
        ];

        $filtered = ViewHelper::filterByReadStatus($articles, true);

        $this->assertCount(2, $filtered);
        $this->assertArrayHasKey('1', $filtered);
        $this->assertArrayHasKey('3', $filtered);
    }

    public function testFilterByReadStatusShowAll(): void
    {
        $articles = [
            '1' => ['t' => 'A1', 'r' => 1],
            '2' => ['t' => 'A2', 'r' => 0],
        ];

        $filtered = ViewHelper::filterByReadStatus($articles, false);

        $this->assertCount(2, $filtered);
    }

    public function testFilterByFeed(): void
    {
        $articles = [
            '1' => ['t' => 'A1', 'f' => 5],
            '2' => ['t' => 'A2', 'f' => 10],
            '3' => ['t' => 'A3', 'f' => 5],
        ];

        $filtered = ViewHelper::filterByFeed($articles, 5);

        $this->assertCount(2, $filtered);
        $this->assertArrayHasKey('1', $filtered);
        $this->assertArrayHasKey('3', $filtered);
    }

    public function testFilterByFeedNoMatch(): void
    {
        $articles = [
            '1' => ['t' => 'A1', 'f' => 5],
        ];

        $filtered = ViewHelper::filterByFeed($articles, 999);

        $this->assertEmpty($filtered);
    }

    public function testSortByDateDescending(): void
    {
        $articles = [
            '1' => ['p' => '2025-01-01 10:00:00'],
            '2' => ['p' => '2025-01-03 10:00:00'],
            '3' => ['p' => '2025-01-02 10:00:00'],
        ];

        $sorted = ViewHelper::sortByDate($articles, true);
        $values = array_values($sorted);

        $this->assertEquals('2025-01-03 10:00:00', $values[0]['p']);
        $this->assertEquals('2025-01-02 10:00:00', $values[1]['p']);
        $this->assertEquals('2025-01-01 10:00:00', $values[2]['p']);
    }

    public function testSortByDateAscending(): void
    {
        $articles = [
            '1' => ['p' => '2025-01-03 10:00:00'],
            '2' => ['p' => '2025-01-01 10:00:00'],
        ];

        $sorted = ViewHelper::sortByDate($articles, false);
        $values = array_values($sorted);

        $this->assertEquals('2025-01-01 10:00:00', $values[0]['p']);
        $this->assertEquals('2025-01-03 10:00:00', $values[1]['p']);
    }

    public function testTruncateDescription(): void
    {
        $short = 'Short description';
        $truncated = ViewHelper::truncateDescription($short);
        $this->assertEquals($short, $truncated);
    }

    public function testTruncateDescriptionLong(): void
    {
        $long = str_repeat('A', 600);
        $truncated = ViewHelper::truncateDescription($long, 500);

        $this->assertLessThanOrEqual(501, mb_strlen($truncated)); // 500 + ellipsis
        $this->assertStringEndsWith('…', $truncated);
    }

    public function testTruncateDescriptionCustomLength(): void
    {
        $text = str_repeat('A', 200);
        $truncated = ViewHelper::truncateDescription($text, 100);

        $this->assertLessThanOrEqual(101, mb_strlen($truncated));
    }

    public function testTruncateDescriptionUTF8(): void
    {
        $utf8 = str_repeat('é', 600);
        $truncated = ViewHelper::truncateDescription($utf8, 500);

        $this->assertLessThanOrEqual(501, mb_strlen($truncated));
    }

    public function testBuildLimitClauseZero(): void
    {
        $limit = ViewHelper::buildLimitClause(0);
        $this->assertEquals('50', $limit);
    }

    public function testBuildLimitClauseFloat(): void
    {
        $limit = ViewHelper::buildLimitClause(25.5);
        $this->assertEquals('25', $limit);
    }

    public function testBuildLimitClauseLargeOffset(): void
    {
        $limit = ViewHelper::buildLimitClause(10, 1000);
        $this->assertEquals('1000, 10', $limit);
    }

    public function testBuildFeedFilterZero(): void
    {
        $filter = ViewHelper::buildFeedFilter(0);
        $this->assertEquals('', $filter);
    }

    public function testBuildFeedFilterFloat(): void
    {
        $filter = ViewHelper::buildFeedFilter(5.5);
        $this->assertEquals(' and F.id=5', $filter);
    }

    public function testSanitizeParamsAllValid(): void
    {
        $params = [
            'nb' => '100',
            'id' => '42',
            'offset' => '50',
        ];

        $sanitized = ViewHelper::sanitizeParams($params);

        $this->assertEquals(100, $sanitized['nb']);
        $this->assertEquals(42, $sanitized['id']);
        $this->assertEquals(50, $sanitized['offset']);
    }

    public function testSanitizeParamsNegativeOffset(): void
    {
        $params = ['offset' => '-10'];
        $sanitized = ViewHelper::sanitizeParams($params);

        // Negative numbers are converted to int, not rejected
        $this->assertEquals(-10, $sanitized['offset']);
    }

    public function testParseArticlesJsonNestedStructure(): void
    {
        $json = '{
            "1": {
                "t": "Article 1",
                "p": "2025-01-01",
                "f": 5,
                "r": 1
            }
        }';
        $parsed = ViewHelper::parseArticlesJson($json);

        $this->assertIsArray($parsed);
        $this->assertArrayHasKey('1', $parsed);
        $this->assertEquals(5, $parsed['1']['f']);
    }

    public function testFormatArticleAllFields(): void
    {
        $input = [
            't' => 'Title',
            'p' => '2025-01-01',
            'd' => 'Description',
            'l' => 'https://example.com',
            'a' => 'Author',
            'f' => 10,
            'n' => 'Feed Name',
            'o' => 'https://feed.com',
            'r' => 0,
        ];

        $formatted = ViewHelper::formatArticle($input);

        $this->assertEquals('Title', $formatted['t']);
        $this->assertEquals('2025-01-01', $formatted['p']);
        $this->assertEquals('Description', $formatted['d']);
        $this->assertEquals('https://example.com', $formatted['l']);
        $this->assertEquals('Author', $formatted['a']);
        $this->assertEquals(10, $formatted['f']);
        $this->assertEquals('Feed Name', $formatted['n']);
        $this->assertEquals('https://feed.com', $formatted['o']);
        $this->assertEquals(0, $formatted['r']);
    }

    public function testFilterByReadStatusMixedStatuses(): void
    {
        $articles = [
            '1' => ['t' => 'A1', 'r' => 1],
            '2' => ['t' => 'A2', 'r' => 0],
            '3' => ['t' => 'A3', 'r' => 1],
            '4' => ['t' => 'A4', 'r' => '1'], // String "1"
            '5' => ['t' => 'A5', 'r' => '0'], // String "0"
        ];

        $filtered = ViewHelper::filterByReadStatus($articles, true);

        $this->assertCount(3, $filtered);
        $this->assertArrayHasKey('1', $filtered);
        $this->assertArrayHasKey('3', $filtered);
        $this->assertArrayHasKey('4', $filtered);
    }

    public function testFilterByFeedMultipleMatches(): void
    {
        $articles = [
            '1' => ['t' => 'A1', 'f' => 5],
            '2' => ['t' => 'A2', 'f' => 5],
            '3' => ['t' => 'A3', 'f' => 5],
            '4' => ['t' => 'A4', 'f' => 10],
        ];

        $filtered = ViewHelper::filterByFeed($articles, 5);

        $this->assertCount(3, $filtered);
    }

    public function testSortByDateWithSameDate(): void
    {
        $articles = [
            '1' => ['p' => '2025-01-01 10:00:00', 't' => 'First'],
            '2' => ['p' => '2025-01-01 10:00:00', 't' => 'Second'],
        ];

        $sorted = ViewHelper::sortByDate($articles, true);

        $this->assertCount(2, $sorted);
    }

    public function testTruncateDescriptionEmptyString(): void
    {
        $result = ViewHelper::truncateDescription('');
        $this->assertEquals('', $result);
    }

    public function testTruncateDescriptionSmallLength(): void
    {
        $result = ViewHelper::truncateDescription('test', 2);
        $this->assertEquals('te…', $result);

        $result2 = ViewHelper::truncateDescription('test', 1);
        $this->assertEquals('t…', $result2);
    }
}
