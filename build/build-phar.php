#!/usr/bin/env php
<?php

declare(strict_types=1);

// ============================================================================
// PharBuilder Class - Encapsulates PHAR building logic
// ============================================================================
final class PharBuilder
{
    private string $root;
    private string $pharFile;
    private string $releaseConfigFile;
    private bool $isOfficialRelease;
    private array $stats = [
        'start_time' => 0,
        'files_added' => 0,
        'total_size' => 0,
        'errors' => [],
    ];

    private array $excludeDirs = [
        '', '.', '..',
        '.git', '.github', '.idea', '.claude', '.vscode', '.agents',
        'docs', 'tests', 'docker', 'local', 'build', 'tools', 'examples', 'fixtures', 'out',
        '.phel-cache', '.phpunit.cache',
    ];

    private array $excludeFiles = [
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

    private array $excludeExtensions = [
        '.log' => true,
    ];

    public function __construct(string $root)
    {
        $this->root = realpath($root) ?: $root;
        $this->pharFile = $this->root . '/phel.phar';
        $this->releaseConfigFile = $this->root . '/.phel-release.php';
        $this->stats['start_time'] = microtime(true);
        $this->isOfficialRelease = $this->checkOfficialRelease();
    }

    public function validate(): void
    {
        if (\ini_get('phar.readonly') === '1') {
            throw new RuntimeException(
                "phar.readonly is enabled.\n"
                . 'Run this script with: php -d phar.readonly=0 build/build-phar.php [path]',
            );
        }

        if (!is_dir($this->root)) {
            throw new RuntimeException("Root directory not found: {$this->root}");
        }

        if (!is_readable($this->root)) {
            throw new RuntimeException("Root directory is not readable: {$this->root}");
        }

        // Check for required files
        $requiredFiles = [
            'vendor/autoload.php',
            'bin/phel',
            'composer.lock',
        ];

        foreach ($requiredFiles as $file) {
            $fullPath = $this->root . '/' . $file;
            if (!file_exists($fullPath)) {
                throw new RuntimeException("Required file not found: {$file}");
            }
        }
    }

    /**
     * Prepare the release configuration
     */
    public function prepareReleaseConfig(): void
    {
        if ($this->isOfficialRelease) {
            file_put_contents($this->releaseConfigFile, "<?php\nreturn true;\n");
        } else {
            if (file_exists($this->releaseConfigFile)) {
                unlink($this->releaseConfigFile);
            }
        }
    }

    /**
     * Clean up any existing PHAR file
     */
    public function cleanup(): void
    {
        if (file_exists($this->pharFile)) {
            if (!unlink($this->pharFile)) {
                throw new RuntimeException("Failed to remove existing PHAR file: {$this->pharFile}");
            }
        }
    }

    /**
     * Pre-compile all stdlib .phel modules into cache/compiled/ so the PHAR
     * ships ready-to-run code. Without this, modules other than phel\core
     * would fail to compile at runtime when phar.readonly=On.
     */
    public function preCompileStdlib(): void
    {
        $cacheDir = $this->root . '/cache';
        $compiledDir = $cacheDir . '/compiled';

        // Clean previous compiled cache to avoid stale entries
        if (is_dir($compiledDir)) {
            $files = glob($compiledDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
        }

        // Build the project using Phel's build command — this compiles all
        // stdlib modules through the normal pipeline, populating the temp cache.
        $exitCode = 0;
        passthru(
            \sprintf('cd %s && php bin/phel build --quiet 2>&1', escapeshellarg($this->root)),
            $exitCode,
        );

        if ($exitCode !== 0) {
            echo "⚠️  phel build exited with code {$exitCode}, attempting to continue\n";
        }

        // Copy the compiled cache from the temp dir to the project-local cache/
        // so the files get bundled into the PHAR.
        $tempCacheDir = sys_get_temp_dir() . '/phel/cache';

        if (!is_dir($tempCacheDir . '/compiled')) {
            echo "⚠️  No compiled cache found at {$tempCacheDir}/compiled\n";
            return;
        }

        if (!is_dir($compiledDir)) {
            mkdir($compiledDir, 0755, true);
        }

        // Copy only stdlib compiled files (phel_* prefix)
        $compiledFiles = glob($tempCacheDir . '/compiled/phel_*');
        if ($compiledFiles === false) {
            return;
        }

        $copiedCount = 0;
        foreach ($compiledFiles as $file) {
            $basename = basename($file);
            if (copy($file, $compiledDir . '/' . $basename)) {
                ++$copiedCount;
            }
        }

        // Copy the compiled index (filtered to only stdlib entries)
        $indexFile = $tempCacheDir . '/compiled-index.php';
        if (file_exists($indexFile)) {
            $indexData = @include $indexFile;
            if (\is_array($indexData) && isset($indexData['entries'])) {
                $stdlibEntries = [];
                foreach ($indexData['entries'] as $namespace => $entry) {
                    if (str_starts_with($namespace, 'phel\\')) {
                        // Rewrite compiled_path to be relative to phar cache dir
                        $entry['compiled_path'] = $compiledDir . '/' . basename($entry['compiled_path']);
                        $stdlibEntries[$namespace] = $entry;
                    }
                }
                $indexData['entries'] = $stdlibEntries;
                file_put_contents(
                    $cacheDir . '/compiled-index.php',
                    '<?php return ' . var_export($indexData, true) . ';',
                );
            }
        }

        echo "📦  Pre-compiled {$copiedCount} stdlib module(s) into cache/compiled/\n";
    }

    /**
     * Build the PHAR archive
     */
    public function build(): void
    {
        $this->validate();
        $this->prepareReleaseConfig();
        $this->preCompileStdlib();
        $this->cleanup();

        try {
            $phar = new Phar($this->pharFile);
            $phar->startBuffering();

            $this->addFiles($phar);
            $this->setStub($phar);
            $this->compressPhar($phar);

            $phar->stopBuffering();

            if (!chmod($this->pharFile, 0755)) {
                throw new RuntimeException('Failed to set executable permissions on PHAR');
            }
        } catch (Exception $e) {
            throw new RuntimeException("Failed to build PHAR: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Generate a summary report
     */
    public function report(): string
    {
        $duration = microtime(true) - $this->stats['start_time'];
        $sizeKb = round($this->stats['total_size'] / 1024, 2);
        $sizeMb = round($this->stats['total_size'] / (1024 * 1024), 3);
        $pharSizeMb = round(filesize($this->pharFile) / (1024 * 1024), 3);
        $compressionRatio = round((1 - filesize($this->pharFile) / $this->stats['total_size']) * 100, 1);

        $durationStr = $this->formatDuration($duration);
        $typeEmoji = $this->isOfficialRelease ? '🚀' : '🧪';
        $typeLabel = $this->isOfficialRelease ? 'Official Release' : 'Beta';

        $report = "{$typeEmoji}  PHAR Build Complete\n\n";
        $report .= "📦  Release Type:    {$typeLabel}\n";
        $report .= "\n";
        $report .= "📊  Build Metrics:\n";
        $report .= "   • Files Added:      {$this->stats['files_added']}\n";
        $report .= "   • Source Size:      {$sizeMb} MB ({$sizeKb} KB)\n";
        $report .= "   • PHAR Size:        {$pharSizeMb} MB\n";
        $report .= "   • Compression:      {$compressionRatio}%\n";
        $report .= "\n";
        $report .= "⏱️  Build Duration:     {$durationStr}\n";

        if (!empty($this->stats['errors'])) {
            $report .= '⚠️  Warnings:           ' . \count($this->stats['errors']) . "\n";
        }

        return $report;
    }

    public function isSuccessful(): bool
    {
        return file_exists($this->pharFile) && is_executable($this->pharFile);
    }

    /**
     * Determine if this is an official release build
     */
    private function checkOfficialRelease(): bool
    {
        $officialRelease = getenv('OFFICIAL_RELEASE');
        return $officialRelease !== false && \in_array(
            strtolower($officialRelease),
            ['1', 'true', 'yes'],
            true,
        );
    }

    private function addFiles(Phar $phar): void
    {
        $excludeDirMap = array_fill_keys($this->excludeDirs, true);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($this->root, FilesystemIterator::FOLLOW_SYMLINKS),
                static function ($current, $key, $iterator) use ($excludeDirMap) {
                    if (!$current->isDir()) {
                        return true;
                    }

                    $basename = $current->getBasename();
                    return !isset($excludeDirMap[$basename]);
                },
            ),
        );

        $totalSize = 0;
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $basename = $file->getBasename();

            if (!$this->shouldIncludeFile($basename)) {
                continue;
            }

            $local = substr($file->getPathname(), \strlen($this->root) + 1);
            try {
                $phar->addFile($file->getPathname(), $local);
                $totalSize += filesize($file->getPathname());
                ++$this->stats['files_added'];
            } catch (Exception $e) {
                $this->stats['errors'][] = "Failed to add file {$local}: {$e->getMessage()}";
            }
        }

        $this->stats['total_size'] = $totalSize;
    }

    /**
     * Determine if a file should be included
     */
    private function shouldIncludeFile(string $basename): bool
    {
        // Check excluded files
        if (isset($this->excludeFiles[$basename])) {
            return false;
        }

        // Check excluded extensions
        $ext = strrchr($basename, '.');
        if ($ext && isset($this->excludeExtensions[$ext])) {
            return false;
        }

        // Skip hidden files (but include .phel-release.php if it exists)
        if ($basename[0] === '.' && $basename !== '.phel-release.php') {
            return false;
        }

        return true;
    }

    /**
     * Set the PHAR stub (entry point)
     */
    private function setStub(Phar $phar): void
    {
        $stub = <<<'EOF'
#!/usr/bin/env php
<?php
Phar::mapPhar('phel.phar');
require_once 'phar://phel.phar/vendor/autoload.php';
require 'phar://phel.phar/bin/phel';
__HALT_COMPILER();
EOF;

        $phar->setStub($stub);
    }

    /**
     * Compress the PHAR archive
     */
    private function compressPhar(Phar $phar): void
    {
        try {
            $phar->compressFiles(Phar::GZ);
        } catch (Exception $e) {
            // Compression is optional, silently skip if not available
        }
    }

    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 2) . 's';
        }

        $minutes = intdiv((int) $seconds, 60);
        $secs = $seconds % 60;

        return $minutes . 'm ' . round($secs, 1) . 's';
    }
}

// ============================================================================
// Main Execution
// ============================================================================
try {
    $root = $argv[1] ?? \dirname(__DIR__);
    if (!is_dir($root)) {
        throw new InvalidArgumentException("Invalid root directory: {$root}");
    }

    $builder = new PharBuilder($root);
    $builder->build();

    if (!$builder->isSuccessful()) {
        throw new RuntimeException('PHAR build completed but file is not executable');
    }

    echo $builder->report();
} catch (Exception $e) {
    throw new RuntimeException($e->getMessage());
}
