#!/usr/bin/env php
<?php

$rootDir = dirname(__DIR__);
$outDir = $rootDir . '/build/out';
$pharFile = $outDir . '/phel.phar';

// Ensure output directory exists
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

// Cleanup
if (file_exists($pharFile)) {
    unlink($pharFile);
}

// Create PHAR
$phar = new Phar($pharFile);
$phar->startBuffering();

// Include `src/`, `bin/`, and runtime `vendor/`
$includePaths = [
    '/src' => $rootDir . '/src',
    '/bin' => $rootDir . '/bin',
    '/vendor' => $rootDir . '/vendor',
];

foreach ($includePaths as $prefix => $basePath) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $file) {
        if (!$file->isFile()) continue;

        $localPath = ltrim($prefix . '/' . substr($file->getPathname(), strlen($basePath) + 1), '/');
        $phar->addFile($file->getPathname(), $localPath);
    }
}

// Create executable stub
$stub = "#!/usr/bin/env php\n" .
    "<?php\n" .
    "Phar::mapPhar('phel.phar');\n" .
    "require 'phar://phel.phar/bin/phel';\n" .
    "__HALT_COMPILER();";

$phar->setStub($stub);
$phar->stopBuffering();

chmod($pharFile, 0755);

echo "✅ PHAR created: {$pharFile}\n";
