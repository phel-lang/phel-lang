<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Infrastructure\Cache\AtomicFileWriter;
use Phel\Build\Infrastructure\Cache\BundledCompiledCache;

use function basename;
use function dirname;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function str_replace;
use function str_starts_with;
use function umask;

/**
 * Exports a populated compiled-code cache into the read-only,
 * content-addressed bundle consumed by {@see BundledCompiledCache}.
 *
 * The normal cache keys compiled files by source path (install-location
 * sensitive) and stores the source content hash in its index. This exporter
 * re-keys every bundled `phel.*` entry by that content hash so a cold run on
 * any machine reuses the precompiled stdlib. All files of a namespace are
 * exported, including secondaries pulled in via `(load ...)`, because they
 * each have their own index entry once the cache is fully built.
 */
final readonly class BundledPrecompiler
{
    private const string BUNDLED_PREFIX = 'phel.';

    public function __construct(
        private AtomicFileWriter $fileWriter = new AtomicFileWriter(),
    ) {}

    /**
     * @return int number of compiled files written into the bundle
     */
    public function exportFromCache(string $cacheDir, BundledCompiledCache $target): int
    {
        $this->ensureDir($target->compiledTarget('probe'));

        $written = 0;
        $exportedEnv = [];
        foreach ($this->loadIndexEntries($cacheDir) as $entry) {
            $namespace = $entry['namespace'];
            if (!str_starts_with($namespace, self::BUNDLED_PREFIX)) {
                continue;
            }

            $compiledPath = $this->resolveCompiledPath($cacheDir, $entry['compiled_path']);
            if ($compiledPath === null) {
                continue;
            }

            $compiledCode = @file_get_contents($compiledPath);
            if ($compiledCode === false) {
                continue;
            }

            if ($this->fileWriter->write($target->compiledTarget($entry['source_hash']), $compiledCode)) {
                ++$written;
            }

            if (!isset($exportedEnv[$namespace])) {
                $exportedEnv[$namespace] = true;
                $this->exportEnvironment($cacheDir, $namespace, $target);
            }
        }

        return $written;
    }

    private function exportEnvironment(string $cacheDir, string $namespace, BundledCompiledCache $target): void
    {
        $envSource = $cacheDir . '/compiled/' . str_replace(['\\', '/'], '_', $namespace) . '.env.php';
        if (!is_file($envSource)) {
            return;
        }

        $envCode = @file_get_contents($envSource);
        if ($envCode !== false) {
            $this->fileWriter->write($target->environmentTarget($namespace), $envCode);
        }
    }

    /**
     * The index stores absolute compiled paths from build time. Prefer them,
     * but fall back to the same basename under this cache dir so a relocated
     * cache (copied between machines) still resolves.
     */
    private function resolveCompiledPath(string $cacheDir, string $compiledPath): ?string
    {
        if (is_file($compiledPath)) {
            return $compiledPath;
        }

        $fallback = $cacheDir . '/compiled/' . basename($compiledPath);

        return is_file($fallback) ? $fallback : null;
    }

    /**
     * @return list<array{namespace: string, source_hash: string, compiled_path: string}>
     */
    private function loadIndexEntries(string $cacheDir): array
    {
        $indexFile = $cacheDir . '/compiled-index.php';
        if (!is_file($indexFile)) {
            return [];
        }

        /** @var mixed $data */
        $data = @include $indexFile;
        if (!is_array($data) || !isset($data['entries']) || !is_array($data['entries'])) {
            return [];
        }

        $entries = [];
        foreach ($data['entries'] as $entry) {
            if (is_array($entry)
                && isset($entry['namespace'], $entry['source_hash'], $entry['compiled_path'])
                && is_string($entry['namespace'])
                && is_string($entry['source_hash'])
                && is_string($entry['compiled_path'])
            ) {
                $entries[] = [
                    'namespace' => $entry['namespace'],
                    'source_hash' => $entry['source_hash'],
                    'compiled_path' => $entry['compiled_path'],
                ];
            }
        }

        return $entries;
    }

    private function ensureDir(string $fileInDir): void
    {
        $dir = dirname($fileInDir);
        if (!is_dir($dir)) {
            $oldUmask = umask(0);
            try {
                @mkdir($dir, 0755, true);
            } finally {
                umask($oldUmask);
            }
        }
    }
}
