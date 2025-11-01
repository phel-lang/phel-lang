<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractFactory;
use Phel\Console\Application\ArgvInputSanitizer;
use Phel\Console\Application\VersionFinder;
use Phel\Console\Infrastructure\ConsoleBootstrap;
use Phel\Filesystem\FilesystemFacadeInterface;

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
        // Check for a build-time config file (used when building PHAR)
        $configFile = __DIR__ . '/../../../.phel-release.php';
        if (file_exists($configFile)) {
            return (bool) require $configFile;
        }

        return (bool) (getenv('OFFICIAL_RELEASE') ?: false);
    }
}
