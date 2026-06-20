#!/usr/bin/env php
<?php

declare(strict_types=1);

// ============================================================================
// PharBuilder Class - Encapsulates PHAR building logic
// ============================================================================
final class PharBuilder
{
    /**
     * Stdlib namespaces precompiled into `.php` siblings shipped next to their
     * `.phel` sources inside the PHAR. `phel.core` is the universal cold-start
     * cost paid by every command and is fully self-contained (it only `:use`s
     * PHP classes and `(load ...)`s its own secondaries), so shipping it adds
     * no transitive-dependency requirements.
     *
     * Other modules compile on demand into the user's cache on first use, so
     * they are left out to keep the PHAR within its size budget. A module may
     * only be added here together with its full transitive `(:require ...)`
     * closure, because a FILE-mode compiled namespace `require_once`s its
     * dependency siblings directly.
     *
     * Each entry matches the source path relative to `src/phel`: an exact file
     * or a directory prefix (covering all `(in-ns ...)` secondaries).
     */
    private const array BUNDLED_STDLIB_PATHS = [
        'core.phel',
        'core/',
    ];
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
        '.git', '.github', '.idea', '.claude', '.codex', '.vscode', '.agents', '.phpbench',
        'docs', 'Doc', 'doc',
        'tests', 'Tests', 'test', 'Test',
        'docker', 'benchmarks', 'bench',
        'local', 'build', 'tools', 'resources', 'examples', 'fixtures', 'out',
        '.phel-cache', '.phpunit.cache',
    ];

    /**
     * Directories excluded only when they sit at the workdir root. Used for
     * names that legitimately appear inside vendor packages — `data` (e.g.
     * symfony/string/Resources/data) is the canonical example.
     */
    private array $excludeDirsAtRoot = [
        'data', 'node_modules', 'var',
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
    ];

    /**
     * Matches versioned doc files like UPGRADE-5.4.md or CHANGELOG-7.0.md
     * shipped by some vendor packages.
     */
    private string $versionedDocPattern = '/^(README|CHANGELOG|CHANGES|UPGRADE|HISTORY)(-[\w.]+)?\.(md|rst|txt)$/i';

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
     * Compile the bundled stdlib modules to PHP ahead of time. The output is
     * a path-structured tree of compiled `.php` under `out/phel/` (the normal
     * `phel build` deployment layout): primaries plus every `(in-ns ...)`
     * secondary, each with the runtime `(load ...)` sibling resolution baked
     * in. `addPrecompiledStdlibSiblings()` later ships the bundled subset of
     * these next to their `.phel` sources inside the PHAR.
     */
    public function preCompileStdlib(): void
    {
        $outDir = $this->root . '/out';

        // rsync excludes out/, so phel build has nowhere to write. Create it
        // up front; otherwise build aborts with a file_put_contents warning.
        if (!is_dir($outDir) && !mkdir($outDir, 0o755, true) && !is_dir($outDir)) {
            throw new RuntimeException("Failed to create build output dir: {$outDir}");
        }

        // Build the project using Phel's build command — this compiles every
        // stdlib module through the normal FILE-mode pipeline into out/phel/.
        $exitCode = 0;
        passthru(
            \sprintf('cd %s && php bin/phel build --quiet 2>&1', escapeshellarg($this->root)),
            $exitCode,
        );

        if ($exitCode !== 0) {
            throw new RuntimeException("phel build failed with exit code {$exitCode}");
        }
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
            $this->addPrecompiledStdlibSiblings($phar);
            $this->addReplStartupFile($phar);
            $this->addExampleTemplates($phar);
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
            foreach ($this->stats['errors'] as $message) {
                $report .= "   • {$message}\n";
            }
        }

        return $report;
    }

    public function isSuccessful(): bool
    {
        return file_exists($this->pharFile) && is_executable($this->pharFile);
    }

    /**
     * Ship the precompiled stdlib `.php` next to their `.phel` sources inside
     * the PHAR, so a cold `phel run`/`phel test` reuses them directly
     * (FileEvaluator's precompiled-sibling fast path) instead of recompiling
     * core on first use.
     *
     * Compiled outputs are matched back to their canonical (kebab-case) source
     * path by content hash, so the namespace munging `phel build` applies to
     * primary filenames (e.g. `http-client` -> `http_client.php`) never causes
     * a sibling to land at the wrong path.
     */
    private function addPrecompiledStdlibSiblings(Phar $phar): void
    {
        $srcPhelDir = $this->root . '/src/phel';
        $outPhelDir = $this->root . '/out/phel';

        if (!is_dir($outPhelDir)) {
            $this->stats['errors'][] = "No precompiled stdlib found at {$outPhelDir}";
            return;
        }

        // Map source content hash -> relative kebab path (e.g. 'core/meta.phel')
        // for the bundled subset only.
        $byHash = [];
        foreach ($this->iteratePhpFiles($srcPhelDir, 'phel') as $absSrc) {
            $relative = substr($absSrc, \strlen($srcPhelDir) + 1);
            if ($this->isBundledStdlibSource($relative)) {
                $byHash[md5((string) file_get_contents($absSrc))] = $relative;
            }
        }

        $count = 0;
        foreach ($this->iteratePhpFiles($outPhelDir, 'php') as $compiled) {
            $sourceSibling = preg_replace('/\.php$/', '.phel', $compiled);
            if ($sourceSibling === null || !is_file($sourceSibling)) {
                continue;
            }

            $relative = $byHash[md5((string) file_get_contents($sourceSibling))] ?? null;
            if ($relative === null) {
                continue;
            }

            $pharPath = 'src/phel/' . preg_replace('/\.(phel|cljc)$/i', '.php', $relative);
            $code = $this->stripInlineSourceMap((string) file_get_contents($compiled));

            $phar->addFromString($pharPath, $code);
            ++$this->stats['files_added'];
            $this->stats['total_size'] += \strlen($code);
            ++$count;
        }

        echo "📦  Bundled {$count} precompiled stdlib file(s) as PHAR siblings\n";
    }

    private function isBundledStdlibSource(string $relativePath): bool
    {
        foreach (self::BUNDLED_STDLIB_PATHS as $entry) {
            if (str_ends_with($entry, '/')) {
                if (str_starts_with($relativePath, $entry)) {
                    return true;
                }
            } elseif ($relativePath === $entry) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes the leading inline source-map comment lines a cache-mode
     * compile prepends (`// <file>` then `// ;;<mappings>`) right after the
     * `<?php` opener. Keeps the shipped siblings lean; runtime errors in the
     * stdlib are rare and still resolve via the on-disk `.phel` source.
     */
    private function stripInlineSourceMap(string $php): string
    {
        $lines = explode("\n", $php);
        $result = [];
        $inHeader = true;

        foreach ($lines as $line) {
            if ($inHeader) {
                $trimmed = ltrim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '<?php')) {
                    $result[] = $line;
                    continue;
                }

                if (str_starts_with($trimmed, '// ')) {
                    continue;
                }

                $inHeader = false;
            }

            $result[] = $line;
        }

        return implode("\n", $result);
    }

    /**
     * Yields every file with the given extension under $dir (recursive).
     *
     * @return iterable<string>
     */
    private function iteratePhpFiles(string $dir, string $extension): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        $suffix = '.' . $extension;
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && str_ends_with($fileInfo->getFilename(), $suffix)) {
                yield $fileInfo->getPathname();
            }
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
        $excludeRootDirMap = array_fill_keys($this->excludeDirsAtRoot, true);
        $excludeFiles = $this->excludeFiles;
        $excludeExtensions = $this->excludeExtensions;
        $versionedDocPattern = $this->versionedDocPattern;
        $rootLen = \strlen($this->root);

        $filter = static function ($current) use (
            $excludeDirMap,
            $excludeRootDirMap,
            $excludeFiles,
            $excludeExtensions,
            $versionedDocPattern,
            $rootLen,
        ): bool {
            $basename = $current->getBasename();

            if ($current->isDir()) {
                $relative = str_replace('\\', '/', substr($current->getPathname(), $rootLen));

                // Root-only excludes — match `data` at the workdir top level
                // without breaking nested vendor dirs like
                // vendor/symfony/string/Resources/data.
                if (isset($excludeRootDirMap[$basename]) && $relative === '/' . $basename) {
                    return false;
                }

                if (isset($excludeDirMap[$basename])) {
                    // Directories under /src/ are first-party source trees and must
                    // never be filtered out by generic excludes like 'test'/'Test'.
                    // Examples: src/phel/test/ (stdlib), src/php/Run/Domain/Test/ (PHP classes).
                    return str_starts_with($relative, '/src/');
                }

                // Default-deny unknown hidden directories anywhere outside /src/
                // and /vendor/ to stop tooling caches (e.g. /.foo, /var/folders/.../T)
                // from leaking into the PHAR.
                if ($basename !== '' && $basename[0] === '.'
                    && !str_starts_with($relative, '/src/')
                    && !str_starts_with($relative, '/vendor/')
                ) {
                    return false;
                }

                return true;
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
     * `resources/` is excluded from the generic walker, but the REPL
     * bootstrap must ship inside the PHAR for `phel repl` to find it.
     */
    private function addReplStartupFile(Phar $phar): void
    {
        $startup = $this->root . '/resources/repl/startup.phel';
        if (!is_file($startup)) {
            return;
        }

        $phar->addFile($startup, 'resources/repl/startup.phel');
        ++$this->stats['files_added'];

        $size = @filesize($startup);
        if ($size !== false) {
            $this->stats['total_size'] += $size;
        }
    }

    /**
     * `resources/` is excluded from the generic walker, but the example app
     * templates must ship inside the PHAR so `phel init --template` works from
     * a standalone phar (the rest of `resources/agents/` stays out).
     */
    private function addExampleTemplates(Phar $phar): void
    {
        $examplesRoot = $this->root . '/resources/agents/examples';
        if (!is_dir($examplesRoot)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($examplesRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $absolute = $fileInfo->getPathname();
            $relative = ltrim(str_replace('\\', '/', substr($absolute, \strlen($this->root))), '/');
            $phar->addFile($absolute, $relative);
            ++$this->stats['files_added'];

            $size = @filesize($absolute);
            if ($size !== false) {
                $this->stats['total_size'] += $size;
            }
        }
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
     * Compress the PHAR archive. Compression is optional, but record the
     * failure in stats.errors so the build report surfaces the warning
     * instead of silently shipping an uncompressed PHAR.
     */
    private function compressPhar(Phar $phar): void
    {
        try {
            $phar->compressFiles(Phar::GZ);
        } catch (Throwable $e) {
            // Catch Throwable, not just Exception, because PHP 8.x can raise
            // BadMethodCallException, ValueError, or PharException variants
            // depending on the underlying gzip support.
            $this->stats['errors'][] = "compressFiles failed: {$e->getMessage()}";
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
