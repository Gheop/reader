<?php

namespace Gheop\Reader;

/**
 * Menu builder for feeds with unread counts
 */
class MenuBuilder
{
    /**
     * Build menu JSON from database results
     *
     * @param array $feedsData Array of feed data from database
     * @return string JSON string
     */
    public static function buildMenuJson(array $feedsData): string
    {
        if (empty($feedsData)) {
            return '{}';
        }

        $json = '{';
        $first = true;

        foreach ($feedsData as $feed) {
            if (!$first) {
                $json .= ',';
            }
            $json .= $feed;
            $first = false;
        }

        $json .= '}';

        return $json;
    }

    /**
     * Parse menu data from JSON
     *
     * @param string $json
     * @return array|null
     */
    public static function parseMenuJson(string $json): ?array
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Count total unread items from menu data
     *
     * @param array $menuData
     * @return int
     */
    public static function countUnreadItems(array $menuData): int
    {
        $total = 0;

        foreach ($menuData as $feed) {
            if (isset($feed['n']) && is_numeric($feed['n'])) {
                $total += (int)$feed['n'];
            }
        }

        return $total;
    }

    /**
     * Filter feeds with unread items
     *
     * @param array $menuData
     * @return array
     */
    public static function getUnreadFeeds(array $menuData): array
    {
        return array_filter($menuData, function($feed) {
            return isset($feed['n']) && $feed['n'] > 0;
        });
    }

    /**
     * Sort feeds by title
     *
     * @param array $menuData
     * @return array
     */
    public static function sortFeedsByTitle(array $menuData): array
    {
        uasort($menuData, function($a, $b) {
            $titleA = $a['t'] ?? '';
            $titleB = $b['t'] ?? '';
            return strcasecmp($titleA, $titleB);
        });

        return $menuData;
    }

    /**
     * Sort feeds by unread count (descending)
     *
     * @param array $menuData
     * @return array
     */
    public static function sortFeedsByUnread(array $menuData): array
    {
        uasort($menuData, function($a, $b) {
            $countA = $a['n'] ?? 0;
            $countB = $b['n'] ?? 0;
            return $countB - $countA; // Descending
        });

        return $menuData;
    }

    /**
     * Get feed by ID from menu data
     *
     * @param array $menuData
     * @param int $feedId
     * @return array|null
     */
    public static function getFeedById(array $menuData, int $feedId): ?array
    {
        return $menuData[$feedId] ?? null;
    }

    /**
     * Validate feed data structure
     *
     * @param array $feed
     * @return bool
     */
    public static function isValidFeedData(array $feed): bool
    {
        // Required fields
        if (!isset($feed['t']) || !isset($feed['n'])) {
            return false;
        }

        // Valid types
        if (!is_string($feed['t']) || !is_numeric($feed['n'])) {
            return false;
        }

        return true;
    }
}
