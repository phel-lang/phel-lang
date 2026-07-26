<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Agent;

use function array_keys;
use function array_merge;
use function is_array;
use function is_string;
use function ksort;

/**
 * Record of what `agent-install` last wrote into `.agents/`: the bundled docs
 * version, plus each installed path against the hash of the file **as shipped**.
 *
 * The shipped hash is what makes an incremental sync possible. A file whose
 * current contents still hash to the recorded value is untouched since we wrote
 * it and is safe to refresh; anything else the user has edited.
 */
final readonly class AgentDocsManifest
{
    /**
     * @param array<string, string> $files relative path => hash of the file as shipped
     */
    public function __construct(
        public string $version,
        public array $files,
    ) {}

    public static function empty(): self
    {
        return new self('', []);
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        $version = $raw['version'] ?? '';
        $rawFiles = $raw['files'] ?? [];

        $files = [];
        if (is_array($rawFiles)) {
            foreach ($rawFiles as $path => $hash) {
                if (is_string($path) && is_string($hash)) {
                    $files[$path] = $hash;
                }
            }
        }

        return new self(is_string($version) ? $version : '', $files);
    }

    /**
     * @return array{version: string, files: array<string, string>}
     */
    public function toArray(): array
    {
        $files = $this->files;
        ksort($files);

        return ['version' => $this->version, 'files' => $files];
    }

    /**
     * The hash of $relativePath as we shipped it, or null when we never wrote it.
     */
    public function shippedHash(string $relativePath): ?string
    {
        return $this->files[$relativePath] ?? null;
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return array_keys($this->files);
    }

    /**
     * @param array<string, string> $files
     */
    public function with(string $version, array $files): self
    {
        // Merged, not replaced: a run without `--with-examples` must not forget
        // that an earlier run installed `examples/`, or uninstall would orphan it.
        return new self($version, array_merge($this->files, $files));
    }
}
