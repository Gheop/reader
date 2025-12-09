#!/usr/bin/env php
<?php
/**
 * Benchmark: Image optimization strategies
 */

echo "=== IMAGE OPTIMIZATION BENCHMARK ===\n\n";

$tmpDir = '/www/reader/tmp';

// Analyze current state
echo "--- Current State ---\n";
$files = glob("$tmpDir/*");
$totalSize = 0;
$webpCount = 0;
$origCount = 0;
$webpSize = 0;
$origSize = 0;

foreach ($files as $file) {
    if (is_file($file)) {
        $size = filesize($file);
        $totalSize += $size;
        if (str_ends_with($file, '.webp')) {
            $webpCount++;
            $webpSize += $size;
        } else {
            $origCount++;
            $origSize += $size;
        }
    }
}

echo "Total files: " . count($files) . "\n";
echo "Total size: " . round($totalSize / 1024 / 1024, 1) . " MB\n";
echo "WebP files: $webpCount (" . round($webpSize / 1024 / 1024, 1) . " MB)\n";
echo "Original files: $origCount (" . round($origSize / 1024 / 1024, 1) . " MB)\n\n";

// Find duplicates (original + webp)
echo "--- Duplicate Analysis (original kept after conversion) ---\n";
$duplicates = [];
$duplicateSize = 0;
foreach ($files as $file) {
    if (!str_ends_with($file, '.webp') && file_exists($file . '.webp')) {
        $duplicates[] = $file;
        $duplicateSize += filesize($file);
    }
}
echo "Originals with WebP version: " . count($duplicates) . "\n";
echo "Wasted space: " . round($duplicateSize / 1024 / 1024, 1) . " MB\n\n";

// Size distribution
echo "--- WebP Size Distribution ---\n";
$sizes = ['<100KB' => 0, '100KB-500KB' => 0, '500KB-1MB' => 0, '1MB-5MB' => 0, '>5MB' => 0];
$largFiles = [];
foreach ($files as $file) {
    if (str_ends_with($file, '.webp')) {
        $size = filesize($file);
        if ($size < 100 * 1024) $sizes['<100KB']++;
        elseif ($size < 500 * 1024) $sizes['100KB-500KB']++;
        elseif ($size < 1024 * 1024) $sizes['500KB-1MB']++;
        elseif ($size < 5 * 1024 * 1024) $sizes['1MB-5MB']++;
        else {
            $sizes['>5MB']++;
            $largFiles[] = ['file' => basename($file), 'size' => $size];
        }
    }
}
foreach ($sizes as $range => $count) {
    echo "$range: $count\n";
}

echo "\n--- Largest WebP files (>5MB, likely animated GIFs) ---\n";
usort($largFiles, fn($a, $b) => $b['size'] - $a['size']);
foreach (array_slice($largFiles, 0, 10) as $f) {
    echo round($f['size'] / 1024 / 1024, 1) . " MB: " . $f['file'] . "\n";
}

// Test optimization on sample files
echo "\n--- Optimization Tests ---\n";

// Pick a few sample files of different sizes
$samples = [];
foreach ($files as $file) {
    if (str_ends_with($file, '.webp')) {
        $size = filesize($file);
        if ($size > 100 * 1024 && $size < 200 * 1024 && count($samples) < 1) {
            $samples['medium'] = $file;
        } elseif ($size > 500 * 1024 && $size < 1024 * 1024 && !isset($samples['large'])) {
            $samples['large'] = $file;
        }
    }
}

if (!empty($samples)) {
    echo "\nTesting re-compression with quality limits:\n";
    foreach ($samples as $type => $file) {
        $origSize = filesize($file);
        echo "\n$type file: " . basename($file) . " (" . round($origSize / 1024) . " KB)\n";

        // Test different quality settings
        foreach ([80, 70, 60] as $quality) {
            $testFile = "/tmp/test_q$quality.webp";
            exec("cwebp -q $quality -m 6 '$file' -o '$testFile' 2>/dev/null");
            if (file_exists($testFile)) {
                $newSize = filesize($testFile);
                $savings = round((1 - $newSize / $origSize) * 100);
                echo "  q=$quality: " . round($newSize / 1024) . " KB ($savings% smaller)\n";
                unlink($testFile);
            }
        }
    }
}

// Recommendations
echo "\n\n=== RECOMMENDATIONS ===\n";
echo "1. Delete " . count($duplicates) . " original files after WebP conversion: " . round($duplicateSize / 1024 / 1024, 1) . " MB savings\n";
echo "2. Add quality limit to cwebp (-q 75): ~20-40% size reduction\n";
echo "3. Add max file size limit for GIF→WebP conversion (skip >2MB animated GIFs)\n";
echo "4. Consider lossy WebP for GIFs with -lossy flag\n";
echo "5. Add periodic cleanup of old images (>90 days)\n";

echo "\n";
