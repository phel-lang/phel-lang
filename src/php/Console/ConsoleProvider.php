<?php

declare(strict_types=1);

namespace Phel\Console;

use Composer\InstalledVersions;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Console\Application\VersionFinder;
use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Console\Infrastructure\Command\ApiCommands;
use Phel\Console\Infrastructure\Command\BuildCommands;
use Phel\Console\Infrastructure\Command\FormatterCommands;
use Phel\Console\Infrastructure\Command\GacelaCommands;
use Phel\Console\Infrastructure\Command\InteropCommands;
use Phel\Console\Infrastructure\Command\LintCommands;
use Phel\Console\Infrastructure\Command\LspCommands;
use Phel\Console\Infrastructure\Command\NreplCommands;
use Phel\Console\Infrastructure\Command\RunCommands;
use Phel\Console\Infrastructure\Command\WatchCommands;
use Phel\Filesystem\FilesystemFacade;

final class ConsoleProvider extends AbstractProvider
{
    public const string COMMANDS = 'COMMANDS';

    public const string FACADE_FILESYSTEM = 'FACADE_FILESYSTEM';

    public const string TAG_COMMIT_HASH = 'TAG_COMMIT_HASH';

    public const string CURRENT_COMMIT = 'CURRENT_COMMIT';

    private const string PACKAGE_NAME = 'phel-lang/phel-lang';

    #[Provides(self::FACADE_FILESYSTEM)]
    public function filesystemFacade(Container $container): FilesystemFacade
    {
        return $container->getLocator()->getRequired(FilesystemFacade::class);
    }

    #[Provides(self::COMMANDS)]
    public function commands(): array
    {
        $commands = [];
        foreach ($this->commandProviders() as $provider) {
            array_push($commands, ...$provider->commands());
        }

        return $commands;
    }

    #[Provides(self::TAG_COMMIT_HASH)]
    public function tagCommitHash(): string
    {
        $hash = $this->execGitCommand('git rev-list -n 1 ' . VersionFinder::LATEST_VERSION);
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

    #[Provides(self::CURRENT_COMMIT)]
    public function currentCommit(): string
    {
        $hash = $this->execGitCommand('git rev-parse --verify HEAD');
        if ($hash !== '') {
            return $hash;
        }

        if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            return '';
        }

        return InstalledVersions::getReference(self::PACKAGE_NAME) ?? '';
    }

    private function execGitCommand(string $command): string
    {
        $output = [];
        @exec($command . ' 2>/dev/null', $output);

        return trim($output[0] ?? '');
    }

    /**
     * @return list<ConsoleCommandProviderInterface>
     */
    private function commandProviders(): array
    {
        return [
            new RunCommands(),
            new InteropCommands(),
            new FormatterCommands(),
            new ApiCommands(),
            new BuildCommands(),
            new GacelaCommands(),
            new NreplCommands(),
            new LintCommands(),
            new LspCommands(),
            new WatchCommands(),
        ];
    }
}
