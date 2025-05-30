#!/usr/bin/env php
<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$pharPath = $projectRoot . '/build/phel.phar';

if (file_exists($pharPath)) {
    unlink($pharPath);
}

$phar = new Phar($pharPath, 0, 'phel.phar');
$phar->startBuffering();

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    $relative = str_replace($projectRoot . '/', '', $file->getPathname());
    if (preg_match('#^(bin|src|vendor)/#', $relative)) {
        $phar->addFile($file->getPathname(), $relative);
    }
}

$stub = <<<'STUB'
#!/usr/bin/env php
<?php
Phar::mapPhar('phel.phar');
require 'phar://phel.phar/bin/phel';
__HALT_COMPILER();
STUB;

$phar->setStub($stub);
$phar->stopBuffering();
chmod($pharPath, 0755);

echo "Created {$pharPath}\n";
