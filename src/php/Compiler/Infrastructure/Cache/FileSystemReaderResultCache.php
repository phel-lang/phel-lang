<?php

declare(strict_types=1);

namespace Phel\Compiler\Infrastructure\Cache;

use Phel\Compiler\Domain\Cache\CachedReaderResult;
use Phel\Compiler\Domain\Cache\ReaderResultCacheInterface;
use Throwable;

use function is_array;

/**
 * Persists serialized reader results under `<cacheDir>/read-result/`.
 *
 * Serialized Phel collections are bulky (each node carries its hasher and
 * equalizer), so the blob is gzip-compressed before it hits disk — that turns a
 * ~100x-over-source payload into ~3x while staying cheaper to inflate than to
 * re-read.
 *
 * The key folds the Phel version and optimization level into the source hash so
 * a compiler upgrade or an `-O` change busts every stale entry automatically.
 */
final readonly class FileSystemReaderResultCache implements ReaderResultCacheInterface
{
    private string $dir;

    public function __construct(
        string $cacheDir,
        private string $phelVersion = '',
    ) {
        $this->dir = rtrim($cacheDir, '/\\') . '/read-result';
    }

    public function load(string $phelCode, int $optimizationLevel): ?array
    {
        $path = $this->pathFor($phelCode, $optimizationLevel);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $decoded = @gzuncompress($raw);
        if ($decoded === false) {
            return null;
        }

        try {
            // No allowed_classes allowlist: the payload is a deep graph of
            // arbitrary Phel\Lang value types, so restricting classes would
            // deserialize them as __PHP_Incomplete_Class. The cache dir is
            // project-local and already trusted to the same degree as the
            // eval'd compiled-PHP cache that lives beside it, so an attacker
            // who can write here already has strictly more leverage there.
            $value = unserialize($decoded);
        } catch (Throwable) {
            return null;
        }

        if (!is_array($value)) {
            return null;
        }

        $entries = [];
        foreach ($value as $item) {
            // A negative delta cannot come from a correct save (you cannot
            // un-consume a gensym); treat it as corruption rather than letting
            // it desync the gensym counter on replay.
            if (!$item instanceof CachedReaderResult || $item->gensymDelta < 0) {
                return null;
            }

            $entries[] = $item;
        }

        return $entries;
    }

    /**
     * @param list<CachedReaderResult> $entries
     */
    public function save(string $phelCode, int $optimizationLevel, array $entries): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0o777, true) && !is_dir($this->dir)) {
            return;
        }

        $blob = gzcompress(serialize($entries), 6);
        if ($blob === false) {
            return;
        }

        $path = $this->pathFor($phelCode, $optimizationLevel);
        // Write to a unique temp file then rename, so a concurrent reader never
        // observes a half-written cache entry.
        $tmp = $path . '.' . uniqid('', true) . '.tmp';
        if (@file_put_contents($tmp, $blob, LOCK_EX) === false) {
            return;
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
        }
    }

    private function pathFor(string $phelCode, int $optimizationLevel): string
    {
        $key = md5($this->phelVersion . '|O' . $optimizationLevel . '|' . $phelCode);

        return $this->dir . '/' . $key . '.cache';
    }
}
