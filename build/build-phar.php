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
        '.git', '.github', '.idea', '.claude', '.vscode',
        'docs', 'tests', 'docker', 'local', 'build', 'tools', 'examples', 'fixtures',
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
        $this->pharFile = $this->root.'/phel.phar';
        $this->releaseConfigFile = $this->root.'/.phel-release.php';
        $this->stats['start_time'] = microtime(true);
        $this->isOfficialRelease = $this->checkOfficialRelease();
    }

    /**
     * Determine if this is an official release build
     */
    private function checkOfficialRelease(): bool
    {
        $officialRelease = getenv('OFFICIAL_RELEASE');
        return $officialRelease !== false && in_array(
                strtolower($officialRelease),
                ['1', 'true', 'yes'],
                true
            );
    }

    public function validate(): void
    {
        if (ini_get('phar.readonly') === '1') {
            throw new RuntimeException(
                "phar.readonly is enabled.\n".
                "Run this script with: php -d phar.readonly=0 build/build-phar.php [path]"
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
            $fullPath = $this->root.'/'.$file;
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
     * Build the PHAR archive
     */
    public function build(): void
    {
        $this->validate();
        $this->prepareReleaseConfig();
        $this->cleanup();

        try {
            $phar = new Phar($this->pharFile);
            $phar->startBuffering();

            $this->addFiles($phar);
            $this->setStub($phar);
            $this->compressPhar($phar);

            $phar->stopBuffering();

            if (!chmod($this->pharFile, 0755)) {
                throw new RuntimeException("Failed to set executable permissions on PHAR");
            }
        } catch (Exception $e) {
            throw new RuntimeException("Failed to build PHAR: {$e->getMessage()}", 0, $e);
        }
    }

    private function addFiles(Phar $phar): void
    {
        $excludeDirMap = array_fill_keys($this->excludeDirs, true);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($this->root, FilesystemIterator::FOLLOW_SYMLINKS),
                function ($current, $key, $iterator) use ($excludeDirMap) {
                    if (!$current->isDir()) {
                        return true;
                    }

                    $basename = $current->getBasename();
                    return !isset($excludeDirMap[$basename]);
                }
            )
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

            $local = substr($file->getPathname(), strlen($this->root) + 1);
            try {
                $phar->addFile($file->getPathname(), $local);
                $totalSize += filesize($file->getPathname());
                $this->stats['files_added']++;
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
            $report .= "⚠️  Warnings:           ".count($this->stats['errors'])."\n";
        }

        return $report;
    }

    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 2).'s';
        }

        $minutes = intdiv((int) $seconds, 60);
        $secs = $seconds % 60;

        return $minutes.'m '.round($secs, 1).'s';
    }

    public function isSuccessful(): bool
    {
        return file_exists($this->pharFile) && is_executable($this->pharFile);
    }
}

// ============================================================================
// Main Execution
// ============================================================================
try {
    $root = $argv[1] ?? dirname(__DIR__);
    if (!is_dir($root)) {
        throw new InvalidArgumentException("Invalid root directory: {$root}");
    }

    $builder = new PharBuilder($root);
    $builder->build();

    if (!$builder->isSuccessful()) {
        throw new RuntimeException("PHAR build completed but file is not executable");
    }

    echo $builder->report();
} catch (Exception $e) {
    throw new RuntimeException($e->getMessage());
}
