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

    /**
     * Basenames skipped at any depth. phar.sh already prunes top-level
     * dev dirs via rsync; the ones listed here catch the same names inside
     * vendor packages (e.g. vendor/foo/tests, vendor/foo/docs).
     */
    private array $excludeDirs = [
        '', '.', '..',
        '.git', '.github', '.idea', '.claude', '.vscode', '.agents', '.phpbench',
        'docs', 'Doc', 'doc',
        'tests', 'Tests', 'test', 'Test',
        'docker', 'benchmarks', 'bench',
        'local', 'build', 'tools', 'examples', 'fixtures', 'out',
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
        'psalm-gacela.xml' => true,
        'rector.php' => true,
        'phpunit.xml.dist' => true,
        'logo_readme.svg' => true,
        'logo.svg' => true,
        'README.md' => true,
        'CHANGELOG.md' => true,
        'AGENTS.md' => true,
        'CONTRIBUTING.md' => true,
        'CLAUDE.md' => true,
        'AUTHORS.md' => true,
        'SECURITY.md' => true,
        'CODE_OF_CONDUCT.md' => true,
        'HISTORY.md' => true,
    ];

    private array $excludeExtensions = [
        '.log' => true,
        '.svg' => true,
        '.bash' => true,
        '.zsh' => true,
        '.fish' => true,
    ];

    /**
     * Matches versioned doc files like UPGRADE-5.4.md or CHANGELOG-7.0.md
     * shipped by some vendor packages.
     */
    private string $versionedDocPattern = '/^(README|CHANGELOG|CHANGES|UPGRADE|HISTORY)(-[\w.]+)?\.(md|rst|txt)$/i';

    private string $stdlibCacheDir;

    public function __construct(string $root)
    {
        $this->root = realpath($root) ?: $root;
        $this->pharFile = $this->root . '/phel.phar';
        $this->releaseConfigFile = $this->root . '/.phel-release.php';
        $this->stats['start_time'] = microtime(true);
        $this->isOfficialRelease = $this->checkOfficialRelease();
        $this->stdlibCacheDir = (string) (getenv('STDLIB_CACHE_DIR') ?: '');
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
        $outDir = $this->root . '/out';

        // Clean previous compiled cache to avoid stale entries
        if (is_dir($compiledDir)) {
            $files = glob($compiledDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
        }

        $hash = $this->stdlibSourceHash();
        if ($this->restoreStdlibFromCache($hash, $cacheDir, $compiledDir)) {
            return;
        }

        // rsync excludes out/, so phel build has nowhere to write. Create it
        // up front; otherwise build aborts with a file_put_contents warning
        // and only appears to succeed because a previous /tmp/phel/cache run
        // is still around.
        if (!is_dir($outDir) && !mkdir($outDir, 0o755, true) && !is_dir($outDir)) {
            throw new RuntimeException("Failed to create build output dir: {$outDir}");
        }

        // Build the project using Phel's build command — this compiles all
        // stdlib modules through the normal pipeline, populating the temp cache.
        $exitCode = 0;
        passthru(
            \sprintf('cd %s && php bin/phel build --quiet 2>&1', escapeshellarg($this->root)),
            $exitCode,
        );

        if ($exitCode !== 0) {
            throw new RuntimeException("phel build failed with exit code {$exitCode}");
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
                        // Drop wall-clock timestamps so the index is deterministic
                        if (\array_key_exists('last_accessed', $entry)) {
                            $entry['last_accessed'] = 0;
                        }
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

        $this->persistStdlibCache($hash, $compiledDir, $cacheDir);
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
            $phar->setSignatureAlgorithm(Phar::SHA256);

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

    private function stdlibSourceHash(): string
    {
        $sourceDir = $this->root . '/src/phel';
        if (!is_dir($sourceDir)) {
            return '';
        }

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iter as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.phel')) {
                $files[] = $f->getPathname();
            }
        }

        sort($files);

        $ctx = hash_init('sha256');
        foreach ($files as $path) {
            hash_update($ctx, substr($path, \strlen($sourceDir)));
            hash_update_file($ctx, $path);
        }

        return hash_final($ctx);
    }

    private function restoreStdlibFromCache(string $hash, string $cacheDir, string $compiledDir): bool
    {
        if ($this->stdlibCacheDir === '' || $hash === '') {
            return false;
        }

        $bucket = $this->stdlibCacheDir . '/' . $hash;
        if (!is_dir($bucket . '/compiled') || !is_file($bucket . '/compiled-index.php')) {
            return false;
        }

        if (!is_dir($compiledDir) && !mkdir($compiledDir, 0o755, true) && !is_dir($compiledDir)) {
            return false;
        }

        $cached = glob($bucket . '/compiled/*');
        if ($cached === false) {
            return false;
        }

        $count = 0;
        foreach ($cached as $file) {
            if (copy($file, $compiledDir . '/' . basename($file))) {
                ++$count;
            }
        }

        copy($bucket . '/compiled-index.php', $cacheDir . '/compiled-index.php');

        echo "📦  Reused {$count} cached stdlib module(s) for hash " . substr($hash, 0, 12) . "\n";
        return true;
    }

    private function persistStdlibCache(string $hash, string $compiledDir, string $cacheDir): void
    {
        if ($this->stdlibCacheDir === '' || $hash === '') {
            return;
        }

        $bucket = $this->stdlibCacheDir . '/' . $hash;
        $bucketCompiled = $bucket . '/compiled';
        if (!is_dir($bucketCompiled) && !mkdir($bucketCompiled, 0o755, true) && !is_dir($bucketCompiled)) {
            return;
        }

        $files = glob($compiledDir . '/*') ?: [];
        foreach ($files as $file) {
            @copy($file, $bucketCompiled . '/' . basename($file));
        }

        $indexFile = $cacheDir . '/compiled-index.php';
        if (is_file($indexFile)) {
            @copy($indexFile, $bucket . '/compiled-index.php');
        }
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
        $excludeFiles = $this->excludeFiles;
        $excludeExtensions = $this->excludeExtensions;
        $versionedDocPattern = $this->versionedDocPattern;
        $rootLen = \strlen($this->root);

        $filter = static function ($current) use (
            $excludeDirMap,
            $excludeFiles,
            $excludeExtensions,
            $versionedDocPattern,
            $rootLen,
        ): bool {
            $basename = $current->getBasename();

            if ($current->isDir()) {
                if (!isset($excludeDirMap[$basename])) {
                    return true;
                }

                // `src/phel/test/` is a stdlib source tree (phel\test\gen,
                // phel\test\selector). Keep it despite the generic 'test' exclude.
                $relative = str_replace('\\', '/', substr($current->getPathname(), $rootLen));
                if ($basename === 'test' && str_starts_with($relative, '/src/phel/')) {
                    return true;
                }

                return false;
            }

            if (isset($excludeFiles[$basename])) {
                return false;
            }

            $ext = strrchr($basename, '.');
            if ($ext !== false && isset($excludeExtensions[$ext])) {
                return false;
            }

            if ($basename[0] === '.' && $basename !== '.phel-release.php') {
                return false;
            }

            if (preg_match($versionedDocPattern, $basename) === 1) {
                return false;
            }

            return true;
        };

        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
                $filter,
            ),
        );

        try {
            $added = $phar->buildFromIterator($iterator, $this->root);
        } catch (Exception $e) {
            $this->stats['errors'][] = "buildFromIterator failed: {$e->getMessage()}";
            return;
        }

        $totalSize = 0;
        foreach ($added as $pharPath => $sourcePath) {
            $size = @filesize($sourcePath);
            if ($size !== false) {
                $totalSize += $size;
            }
        }

        $this->stats['files_added'] = \count($added);
        $this->stats['total_size'] = $totalSize;
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
