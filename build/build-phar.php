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

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::FOLLOW_SYMLINKS),
        function ($current, $key, $iterator) {
            $basename = $current->getBasename();
            $exclude = ['.', '..', '.git', 'docs', 'tests', 'docker', 'local', 'build', 'tools', 'examples', 'fixtures'];
            if ($current->isDir() && in_array($basename, $exclude, true)) {
                return false;
            }
            return $basename[0] !== '.';
        }
    )
);

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $basename = $file->getBasename();
        // Exclude unnecessary files
        $excludeFiles = ['composer.lock', 'composer.json', 'phpstan.neon', '.env', 'phel-debug.log', 'phpbench.json'];
        if (in_array($basename, $excludeFiles, true)) {
            continue;
        }
        // Exclude log files
        if (str_ends_with($basename, '.log')) {
            continue;
        }
        $local = substr($file->getPathname(), strlen($root) + 1);
        $phar->addFile($file->getPathname(), $local);
    }
}

$stub = <<<'EOF'
#!/usr/bin/env php
<?php
Phar::mapPhar('phel.phar');
require_once 'phar://phel.phar/vendor/autoload.php';
require 'phar://phel.phar/bin/phel';
__HALT_COMPILER();
EOF;

$phar->setStub($stub);
$phar->stopBuffering();
chmod($pharFile, 0755);

echo "Created {$pharFile}\n";
