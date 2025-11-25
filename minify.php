#!/usr/bin/env php
<?php
/**
 * Asset Minification Script
 * Uses terser for JavaScript and csso for CSS minification
 *
 * Requirements:
 *   npm install -g terser csso-cli
 */

function minifyJS($input, $output) {
    $cmd = sprintf('terser %s -o %s -c -m 2>&1', escapeshellarg($input), escapeshellarg($output));
    exec($cmd, $outputLines, $returnCode);

    if ($returnCode !== 0) {
        echo "  ⚠️  Error minifying $input: " . implode("\n", $outputLines) . "\n";
        return null;
    }

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
    $cmd = sprintf('csso %s -o %s 2>&1', escapeshellarg($input), escapeshellarg($output));
    exec($cmd, $outputLines, $returnCode);

    if ($returnCode !== 0) {
        echo "  ⚠️  Error minifying $input: " . implode("\n", $outputLines) . "\n";
        return null;
    }

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

echo "=== Asset Minification (terser + csso) ===\n\n";

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

// Minify JavaScript with terser
echo "JavaScript Files (terser):\n";
foreach ($files['js'] as $input => $output) {
    if (!file_exists($input)) {
        echo "  ⚠️  $input not found\n";
        continue;
    }

    $stats = minifyJS($input, $output);
    if ($stats === null) continue;

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

// Minify CSS with csso
echo "CSS Files (csso):\n";
foreach ($files['css'] as $input => $output) {
    if (!file_exists($input)) {
        echo "  ⚠️  $input not found\n";
        continue;
    }

    $stats = minifyCSS($input, $output);
    if ($stats === null) continue;

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
