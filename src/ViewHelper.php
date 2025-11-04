<?php

namespace Gheop\Reader;

/**
 * Helper for view/article display logic
 */
class ViewHelper
{
    /**
     * Parse and validate limit parameter
     *
     * @param mixed $nb Number of items
     * @param mixed $offset Offset for pagination
     * @return string SQL LIMIT clause
     */
    public static function buildLimitClause($nb, $offset = null): string
    {
        $limit = 50; // Default
        $offsetValue = 0;

        if (isset($nb) && is_numeric($nb) && $nb > 0) {
            $limit = min((int)$nb, 100); // Max 100
        }

        if (isset($offset) && is_numeric($offset) && $offset >= 0) {
            $offsetValue = (int)$offset;
            return "$offsetValue, $limit";
        }

        return (string)$limit;
    }

    /**
     * Build filter clause for feed ID
     *
     * @param mixed $id Feed ID
     * @return string SQL WHERE clause fragment
     */
    public static function buildFeedFilter($id): string
    {
        if (isset($id) && is_numeric($id) && $id > 0) {
            return ' and F.id=' . (int)$id;
        }

        return '';
    }

    /**
     * Validate and sanitize view parameters
     *
     * @param array $params Request parameters
     * @return array Sanitized parameters
     */
    public static function sanitizeParams(array $params): array
    {
        return [
            'nb' => isset($params['nb']) && is_numeric($params['nb']) ? (int)$params['nb'] : null,
            'id' => isset($params['id']) && is_numeric($params['id']) ? (int)$params['id'] : null,
            'offset' => isset($params['offset']) && is_numeric($params['offset']) ? (int)$params['offset'] : null,
        ];
    }

    /**
     * Parse article JSON data
     *
     * @param string $json
     * @return array|null
     */
    public static function parseArticlesJson(string $json): ?array
    {
        if (empty($json) || $json === '{}') {
            return [];
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Format article data for display
     *
     * @param array $article
     * @return array
     */
    public static function formatArticle(array $article): array
    {
        return [
            't' => $article['t'] ?? '',              // title
            'p' => $article['p'] ?? '',              // pubdate
            'd' => $article['d'] ?? '',              // description
            'l' => $article['l'] ?? '',              // link
            'a' => $article['a'] ?? '',              // author
            'f' => $article['f'] ?? 0,               // feed id
            'n' => $article['n'] ?? '',              // feed name
            'o' => $article['o'] ?? '',              // feed link
            'r' => $article['r'] ?? 1,               // read status (1 = unread)
        ];
    }

    /**
     * Count articles in result
     *
     * @param array $articles
     * @return int
     */
    public static function countArticles(array $articles): int
    {
        return count($articles);
    }

    /**
     * Filter articles by read status
     *
     * @param array $articles
     * @param bool $unreadOnly
     * @return array
     */
    public static function filterByReadStatus(array $articles, bool $unreadOnly = true): array
    {
        if (!$unreadOnly) {
            return $articles;
        }

        return array_filter($articles, function($article) {
            return isset($article['r']) && $article['r'] == 1;
        });
    }

    /**
     * Get articles from specific feed
     *
     * @param array $articles
     * @param int $feedId
     * @return array
     */
    public static function filterByFeed(array $articles, int $feedId): array
    {
        return array_filter($articles, function($article) use ($feedId) {
            return isset($article['f']) && $article['f'] == $feedId;
        });
    }

    /**
     * Sort articles by date
     *
     * @param array $articles
     * @param bool $descending
     * @return array
     */
    public static function sortByDate(array $articles, bool $descending = true): array
    {
        uasort($articles, function($a, $b) use ($descending) {
            $dateA = strtotime($a['p'] ?? '');
            $dateB = strtotime($b['p'] ?? '');

            if ($descending) {
                return $dateB - $dateA;
            }

            return $dateA - $dateB;
        });

        return $articles;
    }

    /**
     * Truncate description to length
     *
     * @param string $description
     * @param int $maxLength
     * @return string
     */
    public static function truncateDescription(string $description, int $maxLength = 500): string
    {
        if (mb_strlen($description) <= $maxLength) {
            return $description;
        }

        return mb_substr($description, 0, $maxLength) . '…';
    }
}
