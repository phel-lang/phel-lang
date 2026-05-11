<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Shared\PhelProjectDirectory;

use function defined;
use function sprintf;

use const DIRECTORY_SEPARATOR;
use const STDERR;

/**
 * Resolves the REPL history path under `<projectRoot>/.phel/repl-history`,
 * migrating from the legacy `<projectRoot>/.phel-repl-history` location on
 * first call. Set `PHEL_QUIET_MIGRATION=1` to silence the deprecation notice.
 */
final class ReplHistoryPathResolver
{
    public const string FILENAME = 'repl-history';

    public const string LEGACY_FILENAME = '.phel-repl-history';

    public const string QUIET_ENV = 'PHEL_QUIET_MIGRATION';

    /** @var resource|null */
    private $stderr;

    /**
     * @param resource|null $stderr Stream the deprecation notice is written
     *                              to. Defaults to PHP `STDERR` at runtime.
     */
    public function __construct(
        private readonly string $projectRoot,
        $stderr = null,
    ) {
        $this->stderr = $stderr ?? (defined('STDERR') ? STDERR : null);
    }

    public function resolve(): string
    {
        $phelDir = PhelProjectDirectory::ensure($this->projectRoot);
        $newPath = $phelDir . DIRECTORY_SEPARATOR . self::FILENAME;
        $legacyPath = rtrim($this->projectRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . self::LEGACY_FILENAME;

        if (!file_exists($newPath) && is_file($legacyPath) && @rename($legacyPath, $newPath)) {
            $this->emitDeprecation($legacyPath, $newPath);
        }

        return $newPath;
    }

    private function emitDeprecation(string $oldPath, string $newPath): void
    {
        if (getenv(self::QUIET_ENV) === '1' || $this->stderr === null) {
            return;
        }

        @fwrite($this->stderr, sprintf(
            "phel: migrated REPL history %s -> %s (set %s=1 to silence)\n",
            $oldPath,
            $newPath,
            self::QUIET_ENV,
        ));
    }
}
