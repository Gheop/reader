#!/usr/bin/env php
<?php
/**
 * Test Runner for Gheop Reader
 *
 * Usage: php tests/run.php [--filter=TestName]
 *
 * Options:
 *   --filter=Name   Run only tests matching Name
 *   --verbose       Show more details
 */

echo "Gheop Reader Test Suite\n";
echo str_repeat('=', 50) . "\n";

// Parse arguments
$filter = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--filter=')) {
        $filter = substr($arg, 9);
    }
}

// Load test files
require_once __DIR__ . '/IntegrationTest.php';

// Get all test classes
$testClasses = [
    'IntegrationTest',
    'DatabaseTest',
    'FeedDetectorTest',
    'YouTubeHelperTest',
];

// Run tests
foreach ($testClasses as $className) {
    if ($filter && !str_contains($className, $filter)) {
        continue;
    }

    $test = new $className();
    $test->run();
}

// Print summary
TestCase::printSummary();

// Exit with appropriate code
exit(TestCase::getExitCode());
