#!/usr/bin/env bash
set -euo pipefail

echo "🧹 Cleaning old build..."
rm -f build/out/phel.phar

echo "📦 Installing prod dependencies..."
composer install --no-dev --classmap-authoritative

echo "🔨 Building phel.phar..."
php -d phar.readonly=0 build/build-phar.php

echo "✅ Done: build/out/phel.phar"
