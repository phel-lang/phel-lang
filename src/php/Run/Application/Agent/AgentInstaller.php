<?php

declare(strict_types=1);

namespace Phel\Run\Application\Agent;

use Phel\Run\Domain\Agent\AgentDocsManifest;
use Phel\Run\Domain\Agent\AgentDocsSyncResult;
use Phel\Run\Domain\Agent\AgentPlatform;
use RuntimeException;

use function dirname;
use function is_dir;
use function is_file;
use function sprintf;
use function trim;

/**
 * Filesystem work behind `agent-install`: copy/remove a per-platform skill
 * file and the shared `.agents/` docs tree. Pure orchestration with no console
 * dependency, so it can be unit-tested directly; the command renders the
 * outcome it returns.
 */
final readonly class AgentInstaller
{
    public const string UNINSTALL_RESTORED = 'restored';

    public const string UNINSTALL_REMOVED = 'removed';

    public const string UNINSTALL_ABSENT = 'absent';

    public const string AGENTS_DIR = '.agents';

    public const string BACKUP_SUFFIX = '.pre-phel.bak';

    private const string VERSION_FILE = 'VERSION';

    /**
     * How far up from this file to look for `resources/agents/`. The distance
     * differs by install layout (this repo's own checkout, a Composer
     * dependency under `vendor/`, a nested workspace), so walk instead of
     * guessing a level.
     */
    private const int MAX_SOURCE_ROOT_LEVELS = 8;

    public function __construct(
        private AgentDocsSynchronizer $synchronizer = new AgentDocsSynchronizer(),
        private AgentManifestStore $manifestStore = new AgentManifestStore(),
    ) {}

    /**
     * Locate the bundled `resources/agents/` directory.
     */
    public function locateSourceRoot(): string
    {
        for ($levels = 1; $levels <= self::MAX_SOURCE_ROOT_LEVELS; ++$levels) {
            $candidate = dirname(__DIR__, $levels) . '/resources/agents';
            // The VERSION marker ships only with the full agent docs tree, not
            // with the examples-only subtree bundled inside phel.phar, so this
            // keeps reporting the Composer-install hint when run from the PHAR.
            if (is_file($candidate . '/' . self::VERSION_FILE)) {
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

        AgentFileOperations::ensureDirectory(dirname($dst));

        $backedUp = false;
        if (is_file($dst) && !$force) {
            AgentFileOperations::copy($dst, $dst . self::BACKUP_SUFFIX);
            $backedUp = true;
        }

        AgentFileOperations::copy($src, $dst);

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

        AgentFileOperations::delete($dst);

        $backup = $dst . self::BACKUP_SUFFIX;
        if (is_file($backup)) {
            AgentFileOperations::rename($backup, $dst);
            return self::UNINSTALL_RESTORED;
        }

        return self::UNINSTALL_REMOVED;
    }

    /**
     * Bring `.agents/` in line with the bundled tree, file by file. Writes only
     * what is new or stale and never overwrites a local edit unless $force, in
     * which case the edit is backed up first.
     *
     * $dryRun computes the same plan and writes nothing.
     */
    public function syncDocs(
        string $sourceRoot,
        string $projectRoot,
        bool $force,
        bool $withExamples,
        bool $dryRun = false,
    ): AgentDocsSyncResult {
        return $this->synchronizer->sync(
            $sourceRoot,
            $projectRoot . '/' . self::AGENTS_DIR,
            $this->bundledDocsVersion($sourceRoot),
            $force,
            $withExamples,
            $dryRun,
        );
    }

    /**
     * Remove the docs we installed, leaving anything the user added in place.
     * Returns false when there was no `.agents/` to begin with.
     */
    public function removeDocs(string $projectRoot): bool
    {
        $docsDir = $projectRoot . '/' . self::AGENTS_DIR;
        if (!is_dir($docsDir)) {
            return false;
        }

        $this->synchronizer->remove($docsDir);

        return true;
    }

    /**
     * The docs version recorded in the project, or null when `.agents/` was
     * never installed by a release that writes a manifest.
     */
    public function installedDocsVersion(string $projectRoot): ?string
    {
        $manifest = $this->manifestStore->load($projectRoot . '/' . self::AGENTS_DIR);
        if (!$manifest instanceof AgentDocsManifest || $manifest->version === '') {
            return null;
        }

        return $manifest->version;
    }

    public function bundledDocsVersion(string $sourceRoot): string
    {
        $raw = @file_get_contents($sourceRoot . '/' . self::VERSION_FILE);

        return $raw === false ? '' : trim($raw);
    }
}
