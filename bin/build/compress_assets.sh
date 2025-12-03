#!/bin/bash

###
# Pre-compress assets with gzip for nginx gzip_static
# Run this after minifying assets to generate .gz versions
###

echo "Compressing assets with gzip..."

# Compress JS files
for file in *.min.js; do
    if [ -f "$file" ]; then
        gzip -9 -k -f "$file"
        echo "✓ $file -> $file.gz"
    fi
done

# Compress CSS files
for file in themes/*.min.css; do
    if [ -f "$file" ]; then
        gzip -9 -k -f "$file"
        echo "✓ $file -> $file.gz"
    fi
done

# Compress Font Awesome CSS
if [ -f "fontawesome/css/all.min.css" ]; then
    gzip -9 -k -f "fontawesome/css/all.min.css"
    echo "✓ fontawesome/css/all.min.css -> fontawesome/css/all.min.css.gz"
fi

# Compress SVG icon
if [ -f "icon.svg" ]; then
    gzip -9 -k -f "icon.svg"
    echo "✓ icon.svg -> icon.svg.gz"
fi

# Compress manifest
if [ -f "manifest.json" ]; then
    gzip -9 -k -f "manifest.json"
    echo "✓ manifest.json -> manifest.json.gz"
fi

echo ""
echo "Compression complete! Assets are ready for nginx gzip_static."
echo ""
echo "Compression savings:"
du -sb lib.min.js lib.min.js.gz 2>/dev/null | awk 'NR==1{orig=$1} NR==2{comp=$1; saving=int((orig-comp)/orig*100); print "  lib.min.js: " orig " → " comp " bytes (" saving "% smaller)"}'
