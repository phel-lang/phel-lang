<?php

declare(strict_types=1);

namespace Phel\Run\Application\Agent;

use JsonException;
use Phel\Run\Domain\Agent\AgentDocsManifest;

use function is_array;
use function is_file;
use function json_decode;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Reads and writes the install manifest that lives at the root of the installed
 * `.agents/` tree. Absent manifest means "installed before this existed, or not
 * by us", which callers treat as: touch nothing you cannot account for.
 *
 * @internal
 */
final class AgentManifestStore
{
    public const string FILENAME = '.phel-agent-manifest.json';

    public function load(string $docsDir): ?AgentDocsManifest
    {
        $path = $this->path($docsDir);
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            // A hand-mangled manifest is indistinguishable from none: we still
            // cannot prove what we shipped, so fall back to the cautious path
            // rather than trusting half a file.
            return null;
        }

        return is_array($decoded) ? AgentDocsManifest::fromArray($decoded) : null;
    }

    public function save(string $docsDir, AgentDocsManifest $manifest): void
    {
        AgentFileOperations::ensureDirectory($docsDir);
        AgentFileOperations::write(
            $this->path($docsDir),
            json_encode(
                $manifest->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) . "\n",
        );
    }

    public function delete(string $docsDir): void
    {
        $path = $this->path($docsDir);
        if (is_file($path)) {
            AgentFileOperations::delete($path);
        }
    }

    private function path(string $docsDir): string
    {
        return $docsDir . '/' . self::FILENAME;
    }
}
