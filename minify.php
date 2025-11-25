#!/usr/bin/env php
<?php
/**
 * Asset Minification Script
 * Creates minified versions of JS and CSS files for production
 */

function minifyJS($input, $output) {
    $js = file_get_contents($input);

    // Remove comments (single line and multi-line)
    $js = preg_replace('!/\*.*?\*/!s', '', $js);
    $js = preg_replace('/\/\/.*$/m', '', $js);

    // Remove whitespace
    $js = preg_replace('/\s+/', ' ', $js);

    // Remove unnecessary spaces around operators and punctuation
    $js = preg_replace('/\s*([{};,:\[\]\(\)])\s*/', '$1', $js);

    // Remove spaces around operators
    $js = preg_replace('/\s*([=+\-*\/%&|<>!])\s*/', '$1', $js);

    file_put_contents($output, $js);

    $originalSize = filesize($input);
    $minifiedSize = filesize($output);
    $savings = round((1 - $minifiedSize / $originalSize) * 100, 1);

    return [
        'original' => $originalSize,
        'minified' => $minifiedSize,
        'savings' => $savings
    ];
}

function minifyCSS($input, $output) {
    $css = file_get_contents($input);

    // Remove comments
    $css = preg_replace('!/\*.*?\*/!s', '', $css);

    // Remove whitespace
    $css = preg_replace('/\s+/', ' ', $css);

    // Remove spaces around CSS punctuation
    $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);

    // Remove trailing semicolons before }
    $css = preg_replace('/;}/', '}', $css);

    // Remove unnecessary zeros
    $css = preg_replace('/:0(px|em|rem|%|vh|vw)/', ':0', $css);

    file_put_contents($output, $css);

    $originalSize = filesize($input);
    $minifiedSize = filesize($output);
    $savings = round((1 - $minifiedSize / $originalSize) * 100, 1);

    return [
        'original' => $originalSize,
        'minified' => $minifiedSize,
        'savings' => $savings
    ];
}

function formatBytes($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

echo "=== Asset Minification ===\n\n";

$files = [
    'js' => [
        'lib.js' => 'lib.min.js'
    ],
    'css' => [
        'themes/common.css' => 'themes/common.min.css',
        'themes/light.css' => 'themes/light.min.css',
        'themes/dark.css' => 'themes/dark.min.css',
        'themes/adaptive.css' => 'themes/adaptive.min.css',
        'themes/adaptive-smooth.css' => 'themes/adaptive-smooth.min.css',
        'themes/modern.css' => 'themes/modern.min.css'
    ]
];

$totalOriginal = 0;
$totalMinified = 0;

// Minify JavaScript
echo "JavaScript Files:\n";
foreach ($files['js'] as $input => $output) {
    if (!file_exists($input)) {
        echo "  ⚠️  $input not found\n";
        continue;
    }

    $stats = minifyJS($input, $output);
    $totalOriginal += $stats['original'];
    $totalMinified += $stats['minified'];

    echo sprintf(
        "  ✓ %s → %s\n    %s → %s (-%s%%)\n\n",
        $input,
        $output,
        formatBytes($stats['original']),
        formatBytes($stats['minified']),
        $stats['savings']
    );
}

// Minify CSS
echo "CSS Files:\n";
foreach ($files['css'] as $input => $output) {
    if (!file_exists($input)) {
        echo "  ⚠️  $input not found\n";
        continue;
    }

    $stats = minifyCSS($input, $output);
    $totalOriginal += $stats['original'];
    $totalMinified += $stats['minified'];

    echo sprintf(
        "  ✓ %s → %s\n    %s → %s (-%s%%)\n\n",
        $input,
        $output,
        formatBytes($stats['original']),
        formatBytes($stats['minified']),
        $stats['savings']
    );
}

$totalSavings = round((1 - $totalMinified / $totalOriginal) * 100, 1);

echo "=== Summary ===\n";
echo sprintf(
    "Total: %s → %s (-%s%%)\n",
    formatBytes($totalOriginal),
    formatBytes($totalMinified),
    $totalSavings
);
echo sprintf("Savings: %s\n", formatBytes($totalOriginal - $totalMinified));
?>
