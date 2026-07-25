<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Cache;

use Phel\Lint\Application\Cache\LintCache;
use Phel\Shared\Api\Diagnostic;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_dir;
use function json_encode;
use function md5_file;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class LintCacheTest extends TestCase
{
    private const string FINGERPRINT = 'fp-1';

    private string $cacheDir;

    private string $filePath;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/phel-lint-cache-' . uniqid('', true);
        mkdir($this->cacheDir, 0o777, true);
        $this->filePath = $this->cacheDir . '/source.phel';
        file_put_contents($this->filePath, "(ns user)\n");
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
    }

    public function test_it_returns_null_when_file_not_in_index(): void
    {
        $this->writeIndex([]);

        $cache = new LintCache($this->cacheDir, self::FINGERPRINT);

        self::assertNull($cache->get($this->filePath));
    }

    public function test_it_returns_null_when_fingerprint_mismatches(): void
    {
        $this->writeIndex([
            $this->filePath => [
                'hash' => $this->hash(),
                'fingerprint' => 'stale-fingerprint',
                'diagnostics' => [],
            ],
        ]);

        $cache = new LintCache($this->cacheDir, self::FINGERPRINT);

        self::assertNull($cache->get($this->filePath));
    }

    public function test_it_returns_null_when_file_hash_changed(): void
    {
        $this->writeIndex([
            $this->filePath => [
                'hash' => 'deadbeef',
                'fingerprint' => self::FINGERPRINT,
                'diagnostics' => [],
            ],
        ]);

        $cache = new LintCache($this->cacheDir, self::FINGERPRINT);

        self::assertNull($cache->get($this->filePath));
    }

    public function test_it_returns_empty_list_for_cached_clean_file(): void
    {
        $this->writeIndex([
            $this->filePath => [
                'hash' => $this->hash(),
                'fingerprint' => self::FINGERPRINT,
                'diagnostics' => [],
            ],
        ]);

        $cache = new LintCache($this->cacheDir, self::FINGERPRINT);

        self::assertSame([], $cache->get($this->filePath));
    }

    public function test_it_reconstructs_full_diagnostic_from_index(): void
    {
        $this->writeIndex([
            $this->filePath => [
                'hash' => $this->hash(),
                'fingerprint' => self::FINGERPRINT,
                'diagnostics' => [[
                    'code' => 'phel/unused-binding',
                    'severity' => Diagnostic::SEVERITY_ERROR,
                    'message' => 'unused',
                    'uri' => '/explicit/uri.phel',
                    'startLine' => 4,
                    'startCol' => 7,
                    'endLine' => 5,
                    'endCol' => 9,
                ]],
            ],
        ]);

        $cache = new LintCache($this->cacheDir, self::FINGERPRINT);
        $diagnostics = $cache->get($this->filePath);

        self::assertNotNull($diagnostics);
        self::assertCount(1, $diagnostics);
        $diagnostic = $diagnostics[0];
        self::assertSame('phel/unused-binding', $diagnostic->code);
        self::assertSame(Diagnostic::SEVERITY_ERROR, $diagnostic->severity);
        self::assertSame('unused', $diagnostic->message);
        self::assertSame('/explicit/uri.phel', $diagnostic->uri);
        self::assertSame(4, $diagnostic->startLine);
        self::assertSame(7, $diagnostic->startCol);
        self::assertSame(5, $diagnostic->endLine);
        self::assertSame(9, $diagnostic->endCol);
    }

    public function test_it_applies_defensive_defaults_for_malformed_diagnostic(): void
    {
        // Entry has a valid hash + fingerprint, but the diagnostic payload is
        // missing every field. get() must reconstruct a valid Diagnostic using
        // the documented fallbacks rather than throwing.
        $this->writeIndex([
            $this->filePath => [
                'hash' => $this->hash(),
                'fingerprint' => self::FINGERPRINT,
                'diagnostics' => [[]],
            ],
        ]);

        $cache = new LintCache($this->cacheDir, self::FINGERPRINT);
        $diagnostics = $cache->get($this->filePath);

        self::assertNotNull($diagnostics);
        self::assertCount(1, $diagnostics);
        $diagnostic = $diagnostics[0];
        self::assertSame('', $diagnostic->code);
        self::assertSame(Diagnostic::SEVERITY_WARNING, $diagnostic->severity);
        self::assertSame('', $diagnostic->message);
        // uri falls back to the requested file path when absent.
        self::assertSame($this->filePath, $diagnostic->uri);
        self::assertSame(1, $diagnostic->startLine);
        self::assertSame(1, $diagnostic->startCol);
        self::assertSame(1, $diagnostic->endLine);
        self::assertSame(1, $diagnostic->endCol);
    }

    public function test_it_coerces_loosely_typed_diagnostic_fields(): void
    {
        // Numeric positions stored as strings (e.g. hand-edited cache) must be
        // coerced to int, and a numeric code coerced to string.
        $this->writeIndex([
            $this->filePath => [
                'hash' => $this->hash(),
                'fingerprint' => self::FINGERPRINT,
                'diagnostics' => [[
                    'code' => 123,
                    'startLine' => '12',
                    'startCol' => '3',
                ]],
            ],
        ]);

        $cache = new LintCache($this->cacheDir, self::FINGERPRINT);
        $diagnostics = $cache->get($this->filePath);

        self::assertNotNull($diagnostics);
        $diagnostic = $diagnostics[0];
        self::assertSame('123', $diagnostic->code);
        self::assertSame(12, $diagnostic->startLine);
        self::assertSame(3, $diagnostic->startCol);
    }

    public function test_it_returns_null_when_index_json_is_corrupt(): void
    {
        // Completely invalid JSON — ensureLoaded() must swallow the decode
        // error, leaving an empty index, so get() reports a cache miss.
        file_put_contents($this->cacheDir . '/index.json', '{ this is : not json');

        $cache = new LintCache($this->cacheDir, self::FINGERPRINT);

        self::assertNull($cache->get($this->filePath));
    }

    public function test_it_returns_null_when_index_json_is_not_an_object(): void
    {
        file_put_contents($this->cacheDir . '/index.json', '"a bare string"');

        $cache = new LintCache($this->cacheDir, self::FINGERPRINT);

        self::assertNull($cache->get($this->filePath));
    }

    public function test_put_then_get_round_trips_diagnostics(): void
    {
        $cache = new LintCache($this->cacheDir, self::FINGERPRINT);
        $diagnostic = new Diagnostic(
            code: 'phel/redundant-do',
            severity: Diagnostic::SEVERITY_WARNING,
            message: 'redundant do',
            uri: $this->filePath,
            startLine: 2,
            startCol: 1,
            endLine: 2,
            endCol: 10,
        );

        $cache->put($this->filePath, [$diagnostic]);
        $cache->flush();

        $reloaded = new LintCache($this->cacheDir, self::FINGERPRINT);
        $result = $reloaded->get($this->filePath);

        self::assertNotNull($result);
        self::assertCount(1, $result);
        self::assertEquals($diagnostic, $result[0]);
    }

    private function hash(): string
    {
        $hash = md5_file($this->filePath);
        self::assertNotFalse($hash);

        return $hash;
    }

    /**
     * @param array<string, mixed> $index
     */
    private function writeIndex(array $index): void
    {
        file_put_contents($this->cacheDir . '/index.json', json_encode($index));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
