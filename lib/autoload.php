<?php
/**
 * Autoloader for Gheop Reader lib classes
 *
 * Usage: require_once(__DIR__ . '/../lib/autoload.php');
 *
 * This will automatically load classes from:
 * - /lib/Database.php
 * - /lib/Feed/*.php
 * - /lib/Http/*.php
 */

spl_autoload_register(function ($class) {
    // Map class names to files
    $classMap = [
        'Database' => __DIR__ . '/Database.php',
        'HttpClient' => __DIR__ . '/Http/HttpClient.php',
        'FeedDetector' => __DIR__ . '/Feed/FeedDetector.php',
        'YouTubeHelper' => __DIR__ . '/Feed/YouTubeHelper.php',
    ];

    if (isset($classMap[$class])) {
        require_once $classMap[$class];
    }
});

// Also load the Database class immediately for db() helper
require_once __DIR__ . '/Database.php';
