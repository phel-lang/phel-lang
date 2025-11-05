<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractFactory;
use Phel\Console\Application\ArgvInputSanitizer;
use Phel\Console\Application\VersionFinder;
use Phel\Console\Infrastructure\ConsoleBootstrap;
use Phel\Filesystem\FilesystemFacadeInterface;

use function defined;
use function in_array;

final class ConsoleFactory extends AbstractFactory
{
    public const string CONSOLE_NAME = 'Phel';

    public function createConsoleBootstrap(): ConsoleBootstrap
    {
        return new ConsoleBootstrap(
            self::CONSOLE_NAME,
            $this->createVersionFinder()->getVersion(),
        );
    }

    public function getConsoleCommands(): array
    {
        return $this->getProvidedDependency(ConsoleProvider::COMMANDS);
    }

    public function getFilesystemFacade(): FilesystemFacadeInterface
    {
        return $this->getProvidedDependency(ConsoleProvider::FACADE_FILESYSTEM);
    }

    public function createVersionFinder(): VersionFinder
    {
        return new VersionFinder(
            $this->getProvidedDependency(ConsoleProvider::TAG_COMMIT_HASH),
            $this->getProvidedDependency(ConsoleProvider::CURRENT_COMMIT),
            isOfficialRelease: $this->getIsOfficialRelease(),
        );
    }

    public function createArgvInputSanitizer(): ArgvInputSanitizer
    {
        return new ArgvInputSanitizer();
    }

    private function getIsOfficialRelease(): bool
    {
        // First, try to find .phel-release.php at the project root
        $configFile = __DIR__ . '/../../../.phel-release.php';

        if (!file_exists($configFile) && defined('__PHAR_ARCHIVE__')) {
            // If we're running from a PHAR, check the PHAR root
            $configFile = __PHAR_ARCHIVE__ . '/.phel-release.php';
        }

        if (file_exists($configFile)) {
            return (bool) require $configFile;
        }

        // Check environment variable (for local development)
        // Only treat explicit values as true: '1', 'true', 'yes' (case-insensitive)
        $officialRelease = getenv('OFFICIAL_RELEASE');
        if ($officialRelease === false) {
            return false;
        }

        return in_array(strtolower($officialRelease), ['1', 'true', 'yes'], true);
    }
}
