#!/usr/bin/env php
<?php

declare(strict_types=1);

if (ini_get('phar.readonly') === '1') {
    fwrite(STDERR,
        "phar.readonly is enabled.
        Run this script with 'php -d phar.readonly=0 build/build-phar.php [path]'\n");
    exit(1);
}

$root = $argv[1] ?? dirname(__DIR__);
$root = realpath($root);
$pharFile = $root.'/phel.phar';

if (file_exists($pharFile)) {
    unlink($pharFile);
}

$phar = new Phar($pharFile);
$phar->startBuffering();

// Define exclude directories as constants for faster checking
$excludeDirs = [
    '', '.', '..',
    '.git', '.github', '.idea', '.claude',
    'docs', 'tests', 'docker', 'local', 'build', 'tools', 'examples', 'fixtures',
    '.phel-cache', '.phpunit.cache',
];
$excludeDirMap = array_fill_keys($excludeDirs, true);

// Define exclude files
$excludeFiles = [
    'composer.lock' => true,
    'composer.json' => true,
    'phpstan.neon' => true,
    '.env' => true,
    'phel-debug.log' => true,
    'phpbench.json' => true,
    'phel-config-local.php' => true,
    'php-cs-fixer.php' => true,
    'psalm.xml' => true,
    'rector.php' => true,
    'phpunit.xml.dist' => true,
    'logo_readme.svg' => true,
];

// Define exclude extensions (excluding .phel files from non-src directories)
$excludeExtensions = ['.log' => true];

$fileCount = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::FOLLOW_SYMLINKS),
        function ($current, $key, $iterator) use ($excludeDirMap) {
            // Early exit for files
            if (!$current->isDir()) {
                return true;
            }

            $basename = $current->getBasename();

            // Check if directory should be excluded
            return !isset($excludeDirMap[$basename]);
        }
    )
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $basename = $file->getBasename();

    // Check excluded files map (fastest check)
    if (isset($excludeFiles[$basename])) {
        continue;
    }

    // Check excluded extensions
    $ext = strrchr($basename, '.');
    if ($ext && isset($excludeExtensions[$ext])) {
        continue;
    }

    // Skip hidden files
    if ($basename[0] === '.') {
        continue;
    }

    $local = substr($file->getPathname(), strlen($root) + 1);
    $phar->addFile($file->getPathname(), $local);
    $fileCount++;
}

fwrite(STDERR, "Added $fileCount files to PHAR\n");

$stub = <<<'EOF'
#!/usr/bin/env php
<?php
Phar::mapPhar('phel.phar');
require_once 'phar://phel.phar/vendor/autoload.php';
require 'phar://phel.phar/bin/phel';
__HALT_COMPILER();
EOF;

$phar->setStub($stub);

// Compress with GZ if available
try {
    $phar->compressFiles(Phar::GZ);
    fwrite(STDERR, "Applied GZ compression\n");
} catch (Exception $e) {
    fwrite(STDERR, "Warning: Could not compress PHAR: {$e->getMessage()}\n");
}

$phar->stopBuffering();
chmod($pharFile, 0755);

$sizeKb = round(filesize($pharFile) / 1024, 2);
echo "✨ Created {$pharFile} ({$sizeKb} KB)\n";
