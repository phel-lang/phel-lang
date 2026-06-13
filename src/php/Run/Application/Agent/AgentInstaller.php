<?php

declare(strict_types=1);

namespace Phel\Run\Application\Agent;

use FilesystemIterator;
use Phel\Run\Domain\Agent\AgentPlatform;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function dirname;
use function in_array;
use function is_dir;
use function is_file;
use function sprintf;

/**
 * Filesystem work behind `agent-install`: copy/remove a per-platform skill
 * file and the shared `.agents/` docs tree. Pure orchestration with no console
 * dependency, so it can be unit-tested directly; the command renders the
 * outcome it returns.
 */
final class AgentInstaller
{
    public const string UNINSTALL_RESTORED = 'restored';

    public const string UNINSTALL_REMOVED = 'removed';

    public const string UNINSTALL_ABSENT = 'absent';

    public const string AGENTS_DIR = '.agents';

    public const string BACKUP_SUFFIX = '.pre-phel.bak';

    private const string EXAMPLES_SUBDIR = 'examples';

    /**
     * Locate the bundled `resources/agents/` directory. The levels differ by
     * install layout: 5 = running from a Composer dependency (vendor/),
     * 4 = running from this repo's own checkout, 6 = nested edge cases.
     */
    public function locateSourceRoot(): string
    {
        foreach ([5, 4, 6] as $levels) {
            $candidate = dirname(__DIR__, $levels) . '/resources/agents';
            // The VERSION marker ships only with the full agent docs tree, not
            // with the examples-only subtree bundled inside phel.phar, so this
            // keeps reporting the Composer-install hint when run from the PHAR.
            if (is_file($candidate . '/VERSION')) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Cannot locate bundled resources/agents/ directory. '
            . 'The downstream agent docs tree is not shipped inside phel.phar; install phel-lang via '
            . 'Composer (composer require phel-lang/phel-lang) and run agent-install from '
            . './vendor/bin/phel instead.',
        );
    }

    /**
     * Copy the platform's skill file into the project. Returns true when an
     * existing target was backed up to `.pre-phel.bak` first.
     */
    public function installSkill(string $sourceRoot, string $projectRoot, AgentPlatform $platform, bool $force): bool
    {
        $src = $sourceRoot . '/' . $platform->source;
        $dst = $projectRoot . '/' . $platform->target;

        if (!is_file($src)) {
            throw new RuntimeException(sprintf('Source skill file not found: %s', $src));
        }

        $this->ensureDir(dirname($dst));

        $backedUp = false;
        if (is_file($dst) && !$force) {
            copy($dst, $dst . self::BACKUP_SUFFIX);
            $backedUp = true;
        }

        copy($src, $dst);

        return $backedUp;
    }

    /**
     * @return self::UNINSTALL_* whether the skill was restored from backup,
     *                           plainly removed, or was not installed
     */
    public function uninstallSkill(string $projectRoot, AgentPlatform $platform): string
    {
        $dst = $projectRoot . '/' . $platform->target;
        if (!is_file($dst)) {
            return self::UNINSTALL_ABSENT;
        }

        unlink($dst);

        $backup = $dst . self::BACKUP_SUFFIX;
        if (is_file($backup)) {
            rename($backup, $dst);
            return self::UNINSTALL_RESTORED;
        }

        return self::UNINSTALL_REMOVED;
    }

    /**
     * Copy the docs tree. Returns false (skipped) when `.agents/` already
     * exists and $force is off; true when the tree was written.
     */
    public function copyDocs(string $sourceRoot, string $projectRoot, bool $force, bool $withExamples): bool
    {
        $dst = $projectRoot . '/' . self::AGENTS_DIR;
        if (is_dir($dst) && !$force) {
            return false;
        }

        $skipTopLevel = $withExamples ? [] : [self::EXAMPLES_SUBDIR];
        $this->recursiveCopy($sourceRoot, $dst, $skipTopLevel);

        return true;
    }

    public function removeDocs(string $projectRoot): bool
    {
        $dst = $projectRoot . '/' . self::AGENTS_DIR;
        if (!is_dir($dst)) {
            return false;
        }

        $this->recursiveRemove($dst);

        return true;
    }

    /**
     * @param list<string> $skipTopLevel
     */
    private function recursiveCopy(string $src, string $dst, array $skipTopLevel): void
    {
        $this->ensureDir($dst);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }

            $sub = $iterator->getSubPathname();
            if (in_array(explode('/', $sub, 2)[0], $skipTopLevel, true)) {
                continue;
            }

            $target = $dst . '/' . $sub;
            if ($item->isDir()) {
                $this->ensureDir($target);
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function recursiveRemove(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }

            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Cannot create directory: %s', $dir));
        }
    }
}
