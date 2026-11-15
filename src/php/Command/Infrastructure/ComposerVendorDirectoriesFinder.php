<?php

declare(strict_types=1);

namespace Phel\Command\Infrastructure;

use Closure;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel;
use Phel\Command\CommandFacade;
use Phel\Command\Domain\Finder\VendorDirectoriesFinderInterface;
use Phel\Config\PhelConfig;
use Phel\Shared\ScalarCoercion;
use Phel\Shared\VersionResolver;
use RuntimeException;

use function dirname;
use function file_get_contents;
use function fwrite;
use function glob;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function ltrim;
use function preg_replace;
use function sprintf;

use const STDERR;

/**
 * @method CommandFacade getFacade()
 *
 * @internal
 */
#[ServiceMap(method: 'getFacade', className: CommandFacade::class)]
final class ComposerVendorDirectoriesFinder implements VendorDirectoriesFinderInterface
{
    use ServiceResolverAwareTrait;

    private const string PHEL_PACKAGE = 'phel-lang/phel-lang';

    /** @var array<string, true> vendor dirs already warned about in this process */
    private static array $warnedVendorDirs = [];

    private readonly Closure $warningWriter;

    /**
     * @param (Closure(string): void)|null $warningWriter
     */
    public function __construct(
        private readonly string $vendorDirectory,
        private readonly bool $runningFromPhar = false,
        private readonly string $runningVersion = '',
        ?Closure $warningWriter = null,
    ) {
        $this->warningWriter = $warningWriter ?? static function (string $message): void {
            fwrite(STDERR, $message);
        };
    }

    /**
     * @return list<string>
     */
    public function findPhelSourceDirectories(): array
    {
        $vendorDir = $this->vendorDirectory;
        $pattern = $vendorDir . '/*/*/' . Phel::PHEL_CONFIG_FILE_NAME;

        $result = [];

        $configPaths = glob($pattern) ?: [];

        foreach ($configPaths as $phelConfigPath) {
            // Phel's own package: under Composer it is the running Phel, already
            // scanned as the internal source dir; under a PHAR it is a second
            // copy whose stdlib would override the bundled one.
            if ($phelConfigPath === $vendorDir . '/' . self::PHEL_PACKAGE . '/' . Phel::PHEL_CONFIG_FILE_NAME) {
                $this->warnAboutSecondPhel();
                continue;
            }

            try {
                $config = $this->getFacade()->readPhelConfig($phelConfigPath);
            } catch (RuntimeException) {
                $this->triggerNotice($phelConfigPath);
                continue;
            }

            $sourceDirectories = ScalarCoercion::toStringList($config[PhelConfig::SRC_DIRS] ?? null);

            foreach ($sourceDirectories as $sourceDirectory) {
                $result[] = dirname($phelConfigPath) . '/' . $sourceDirectory;
            }
        }

        return $result;
    }

    public function triggerNotice(string $phelConfigPath): void
    {
        $message = sprintf(
            'The "%s" must return an array or a PhelConfig object. Path: %s',
            Phel::PHEL_CONFIG_FILE_NAME,
            $phelConfigPath,
        );

        trigger_error($message);
    }

    private function warnAboutSecondPhel(): void
    {
        if (!$this->runningFromPhar || isset(self::$warnedVendorDirs[$this->vendorDirectory])) {
            return;
        }

        self::$warnedVendorDirs[$this->vendorDirectory] = true;

        $vendoredVersion = $this->vendoredPhelVersion();
        $runningVersion = ltrim($this->runningVersion !== '' ? $this->runningVersion : VersionResolver::current(), 'v');
        if ($vendoredVersion === $this->releaseOf($runningVersion)) {
            return;
        }

        ($this->warningWriter)(sprintf(
            "warning: phel.phar %s ignores the %s %s that Composer installed in vendor/, often as a dependency of a Phel library. Run ./vendor/bin/phel to use that version instead.\n",
            $runningVersion,
            self::PHEL_PACKAGE,
            $vendoredVersion ?? '(unknown version)',
        ));
    }

    /**
     * A dev build reads `1.0.0-beta#<commit>`; only `1.0.0` can match a vendored release.
     */
    private function releaseOf(string $version): string
    {
        return preg_replace('/-beta#.*$/', '', $version) ?? $version;
    }

    private function vendoredPhelVersion(): ?string
    {
        $installed = $this->vendorDirectory . '/composer/installed.json';
        if (!is_file($installed)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($installed), true);
        $packages = is_array($data) && is_array($data['packages'] ?? null) ? $data['packages'] : $data;
        if (!is_array($packages)) {
            return null;
        }

        foreach ($packages as $package) {
            if (is_array($package) && ($package['name'] ?? null) === self::PHEL_PACKAGE && is_string($package['version'] ?? null)) {
                return ltrim($package['version'], 'v');
            }
        }

        return null;
    }
}
