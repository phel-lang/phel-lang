<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Cache;

use Phel\Api\Transfer\Diagnostic;

use Throwable;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;

use function md5_file;
use function mkdir;

use const JSON_THROW_ON_ERROR;

/**
 * Incremental lint cache: keyed by file-hash + analyzer version + rule-set
 * fingerprint so stale entries self-invalidate. Stores per-file diagnostic
 * payloads under `.phel/lint-cache/`. v1 uses a single JSON index file.
 *
 * The cache is opt-in: callers pass an absolute base directory (usually
 * the project root + `.phel/lint-cache/`). When the directory cannot be
 * created we degrade silently — lint still works, just without caching.
 */
final class LintCache
{
    private const string INDEX_FILE = 'index.json';

    /** @var array<string, array{hash: string, fingerprint: string, diagnostics: list<array<string, mixed>>}>|null */
    private ?array $index = null;

    public function __construct(
        private readonly string $cacheDir,
        private readonly string $fingerprint,
    ) {}

    /**
     * @return ?list<Diagnostic>
     */
    public function get(string $filePath): ?array
    {
        $this->ensureLoaded();
        if (!isset($this->index[$filePath])) {
            return null;
        }

        $entry = $this->index[$filePath];
        if ($entry['fingerprint'] !== $this->fingerprint) {
            return null;
        }

        $currentHash = $this->hashOf($filePath);
        if ($currentHash === null || $currentHash !== $entry['hash']) {
            return null;
        }

        $diagnostics = [];
        foreach ($entry['diagnostics'] as $data) {
            $diagnostics[] = new Diagnostic(
                code: (string) ($data['code'] ?? ''),
                severity: (string) ($data['severity'] ?? Diagnostic::SEVERITY_WARNING),
                message: (string) ($data['message'] ?? ''),
                uri: (string) ($data['uri'] ?? $filePath),
                startLine: (int) ($data['startLine'] ?? 1),
                startCol: (int) ($data['startCol'] ?? 1),
                endLine: (int) ($data['endLine'] ?? 1),
                endCol: (int) ($data['endCol'] ?? 1),
            );
        }

        return $diagnostics;
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    public function put(string $filePath, array $diagnostics): void
    {
        $this->ensureLoaded();
        $hash = $this->hashOf($filePath);
        if ($hash === null) {
            return;
        }

        $payload = [];
        foreach ($diagnostics as $diagnostic) {
            $payload[] = $diagnostic->toArray();
        }

        $this->index[$filePath] = [
            'hash' => $hash,
            'fingerprint' => $this->fingerprint,
            'diagnostics' => $payload,
        ];
    }

    public function flush(): void
    {
        if ($this->index === null) {
            return;
        }

        if (!$this->ensureCacheDir()) {
            return;
        }

        $indexPath = $this->indexPath();
        @file_put_contents(
            $indexPath,
            json_encode($this->index, JSON_THROW_ON_ERROR),
        );
    }

    private function ensureLoaded(): void
    {
        if ($this->index !== null) {
            return;
        }

        $this->index = [];
        $indexPath = $this->indexPath();
        if (!file_exists($indexPath)) {
            return;
        }

        $raw = @file_get_contents($indexPath);
        if ($raw === false) {
            return;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return;
        }

        if (!is_array($decoded)) {
            return;
        }

        /** @var array<string, array{hash: string, fingerprint: string, diagnostics: list<array<string, mixed>>}> $decoded */
        $this->index = $decoded;
    }

    private function hashOf(string $filePath): ?string
    {
        $hash = @md5_file($filePath);

        return $hash === false ? null : $hash;
    }

    private function ensureCacheDir(): bool
    {
        if (is_dir($this->cacheDir)) {
            return true;
        }

        return @mkdir($this->cacheDir, 0o755, true) || is_dir($this->cacheDir);
    }

    private function indexPath(): string
    {
        return rtrim($this->cacheDir, '/') . '/' . self::INDEX_FILE;
    }
}
