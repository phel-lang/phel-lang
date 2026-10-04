<?php

declare(strict_types=1);

namespace Phel\Shared;

use Composer\InstalledVersions;

use function class_exists;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_exists;
use function getenv;
use function in_array;
use function strtolower;
use function trim;

/**
 * Resolves the running Phel version string from the ambient environment: Phel's
 * own git checkout, then Composer's installed metadata, then the build-time
 * official-release marker. Self-contained (no module state) so any module can
 * instantiate it directly — this is what lets Run report its version without
 * depending on the Console module.
 */
final readonly class VersionResolver
{
    private const string PACKAGE_NAME = 'phel-lang/phel-lang';

    private string $phelRoot;

    public function __construct(?string $phelRoot = null)
    {
        $this->phelRoot = $phelRoot ?? dirname(__DIR__, 3);
    }

    public static function current(): string
    {
        static $version = null;

        return $version ??= new self()->resolve();
    }

    public static function currentReference(): string
    {
        static $reference = null;

        return $reference ??= new self()->currentCommit();
    }

    public function resolve(): string
    {
        return new VersionFinder(
            $this->tagCommitHash(),
            $this->currentCommit(),
            isOfficialRelease: $this->isOfficialRelease(),
        )->getVersion();
    }

    private function tagCommitHash(): string
    {
        $hash = $this->execGitCommand('rev-list -n 1 ' . VersionFinder::LATEST_VERSION);
        if ($hash !== '') {
            return $hash;
        }

        if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            return '';
        }

        if (InstalledVersions::getPrettyVersion(self::PACKAGE_NAME) !== VersionFinder::LATEST_VERSION) {
            return '';
        }

        return InstalledVersions::getReference(self::PACKAGE_NAME) ?? '';
    }

    private function currentCommit(): string
    {
        $hash = $this->execGitCommand('rev-parse --verify HEAD');
        if ($hash !== '') {
            return $hash;
        }

        if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            return '';
        }

        return InstalledVersions::getReference(self::PACKAGE_NAME) ?? '';
    }

    private function isOfficialRelease(): bool
    {
        // Build-time marker written when packaging the PHAR.
        $configFile = $this->phelRoot . '/.phel-release.php';
        if (file_exists($configFile)) {
            return (bool) require $configFile;
        }

        // Local development fall-back: only explicit truthy values count.
        $officialRelease = getenv('OFFICIAL_RELEASE');
        if ($officialRelease === false) {
            return false;
        }

        return in_array(strtolower($officialRelease), ['1', 'true', 'yes'], true);
    }

    /**
     * Runs a git command in Phel's own checkout and returns its first output
     * line trimmed, or an empty string when Phel is not a git checkout (a
     * Composer install or the PHAR), git is unavailable, or the command fails.
     * Callers treat the empty result as the signal to fall back to Composer's
     * InstalledVersions. Never the current directory: that is the user's
     * project, whose commit is not Phel's version.
     */
    private function execGitCommand(string $command): string
    {
        if (!file_exists($this->phelRoot . '/.git')) {
            return '';
        }

        $output = [];
        @exec('git -C ' . escapeshellarg($this->phelRoot) . ' ' . $command . ' 2>/dev/null', $output);

        return trim($output[0] ?? '');
    }
}
