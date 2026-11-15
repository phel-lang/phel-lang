<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Doctor;

use function implode;
use function ksort;
use function ltrim;
use function sprintf;

/**
 * What `phel doctor` says about how Phel is installed and what the project
 * pulls from `vendor/`.
 *
 * @internal
 */
final readonly class InstallationReport
{
    private const string PHEL_PACKAGE = 'phel-lang/phel-lang';

    /**
     * @param array<string, list<string>> $phelLibraries    vendor package name => its Phel source dirs
     * @param array<string, string>       $vendoredPackages package name => version, from the project's vendor/
     * @param array<string, string>       $bundledPackages  package name => version, bundled in the PHAR
     */
    public function __construct(
        private InstallMode $mode,
        private string $location,
        private string $version,
        private bool $autoloaderPresent,
        private bool $autoloaderLoaded,
        private array $phelLibraries,
        private array $vendoredPackages,
        private array $bundledPackages = [],
        private string $vendorName = 'vendor',
        private ?string $libraryScanError = null,
    ) {}

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return [
            sprintf(' - Phel: %s from %s (%s)', ltrim($this->version, 'v'), $this->mode->label(), $this->location),
            $this->autoloaderLine(),
            ...$this->libraryLines(),
            ...$this->conflictLines(),
        ];
    }

    private function autoloaderLine(): string
    {
        if (!$this->autoloaderPresent) {
            return sprintf(' - Project autoloader: none (no %s/autoload.php)', $this->vendorName);
        }

        return $this->autoloaderLoaded
            ? sprintf(' - Project autoloader: <info>OK</info> %s/autoload.php loaded', $this->vendorName)
            : sprintf(' - Project autoloader: <comment>WARN</comment> %s/autoload.php exists but was not loaded', $this->vendorName);
    }

    /**
     * @return list<string>
     */
    private function libraryLines(): array
    {
        if ($this->libraryScanError !== null) {
            return [sprintf(' - Phel libraries in %s/: <comment>WARN</comment> could not read them: %s', $this->vendorName, $this->libraryScanError)];
        }

        if ($this->phelLibraries === []) {
            return [sprintf(' - Phel libraries in %s/: none', $this->vendorName)];
        }

        $libraries = $this->phelLibraries;
        ksort($libraries);

        $lines = [sprintf(' - Phel libraries in %s/:', $this->vendorName)];
        foreach ($libraries as $name => $dirs) {
            $lines[] = sprintf('   - %s (%s)', $name, implode(', ', $dirs));
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function conflictLines(): array
    {
        $lines = [];

        $vendoredPhel = $this->vendoredPackages[self::PHEL_PACKAGE] ?? null;
        if ($this->mode === InstallMode::Phar && $vendoredPhel !== null) {
            $lines[] = sprintf(
                ' - <comment>WARN</comment> %s/ also holds %s %s, often as a dependency of a Phel library. phel.phar ignores it; run ./%s/bin/phel to use it.',
                $this->vendorName,
                self::PHEL_PACKAGE,
                ltrim($vendoredPhel, 'v'),
                $this->vendorName,
            );
        }

        $vendored = $this->vendoredPackages;
        ksort($vendored);
        foreach ($vendored as $name => $version) {
            $bundled = $this->bundledPackages[$name] ?? null;
            if ($name === self::PHEL_PACKAGE || $bundled === null || ltrim($bundled, 'v') === ltrim($version, 'v')) {
                continue;
            }

            $lines[] = sprintf(
                ' - <comment>WARN</comment> %s %s in %s/ differs from the %s bundled in phel.phar. Classes load from phel.phar first, so your code gets %s.',
                $name,
                ltrim($version, 'v'),
                $this->vendorName,
                ltrim($bundled, 'v'),
                ltrim($bundled, 'v'),
            );
        }

        return $lines;
    }
}
