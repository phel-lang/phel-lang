<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use PhelTest\Integration\Util\DirectoryUtil;

use function dirname;
use function getmypid;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Isolated, per-process project root for `bin/phel build` tests.
 *
 * Build tests must never write their compiled output tree inside the repo:
 * NamespaceLoader scans getcwd() (the repo root), so a sibling paratest worker
 * mid-build would surface this test's `out/phel/core.phel` as a duplicate of
 * the real `src/phel/core.phel` and load the wrong file. Building under
 * sys_get_temp_dir() — already worker-private via the paratest bootstrap —
 * keeps the output, and any fixture the test mutates, off every scan root.
 */
final readonly class BuildCommandWorkspace
{
    private const string COMMAND_DIR = __DIR__;

    private string $root;

    /**
     * @param bool $shared keep the same root across a class's process-isolated
     *                     methods (needed for `#[Depends]` chains); within a
     *                     worker the label is unique, across workers TMPDIR is
     */
    public function __construct(string $label, bool $shared = false)
    {
        $base = sys_get_temp_dir() . '/phel-build-' . $label;
        $this->root = $shared ? $base : $base . '-' . getmypid() . '-' . uniqid();
        @mkdir($this->root, 0o777, true);
    }

    public function root(): string
    {
        return $this->root;
    }

    public function path(string $relative): string
    {
        return $this->root . '/' . $relative;
    }

    /**
     * Copy a fixture file or directory from the Command dir into the same
     * relative location under the workspace root.
     */
    public function import(string $relative): self
    {
        DirectoryUtil::copyPath(self::COMMAND_DIR . '/' . $relative, $this->root . '/' . $relative);

        return $this;
    }

    public function writeFile(string $relative, string $contents): self
    {
        $target = $this->root . '/' . $relative;
        @mkdir(dirname($target), 0o777, true);
        file_put_contents($target, $contents);

        return $this;
    }

    public function remove(): void
    {
        DirectoryUtil::removeDir($this->root);
    }
}
