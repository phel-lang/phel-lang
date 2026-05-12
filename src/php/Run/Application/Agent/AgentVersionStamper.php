<?php

declare(strict_types=1);

namespace Phel\Run\Application\Agent;

use function file_get_contents;
use function is_file;
use function preg_match;
use function preg_replace;
use function rtrim;
use function sprintf;
use function trim;

final readonly class AgentVersionStamper
{
    public const string STAMP_PATTERN = '/\n?<!-- phel-agents v[^>]*-->\s*$/';

    public const string STAMP_EXTRACT_PATTERN = '/<!-- phel-agents v([^\s>]+) -->/';

    public const string VERSION_FILE = 'VERSION';

    public function __construct(private string $sourceRoot) {}

    public function currentVersion(): ?string
    {
        $versionFile = $this->sourceRoot . '/' . self::VERSION_FILE;
        if (!is_file($versionFile)) {
            return null;
        }

        $version = trim((string) file_get_contents($versionFile));
        return $version === '' ? null : $version;
    }

    public function installedVersion(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = (string) file_get_contents($path);
        if (preg_match(self::STAMP_EXTRACT_PATTERN, $contents, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public function stamp(string $contents): string
    {
        $version = $this->currentVersion();
        if ($version === null) {
            return $contents;
        }

        $stripped = (string) preg_replace(self::STAMP_PATTERN, '', $contents);
        return rtrim($stripped) . sprintf("\n\n<!-- phel-agents v%s -->\n", $version);
    }
}
