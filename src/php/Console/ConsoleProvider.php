<?php

declare(strict_types=1);

namespace Phel\Console;

use Composer\InstalledVersions;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use Phel\Api\Infrastructure\Command\DocCommand;
use Phel\Build\Infrastructure\Command\BuildCommand;
use Phel\Console\Application\VersionFinder;
use Phel\Filesystem\FilesystemFacade;
use Phel\Formatter\Infrastructure\Command\FormatCommand;
use Phel\Interop\Infrastructure\Command\ExportCommand;
use Phel\Run\Infrastructure\Command\DoctorCommand;
use Phel\Run\Infrastructure\Command\EvalCommand;
use Phel\Run\Infrastructure\Command\NsCommand;
use Phel\Run\Infrastructure\Command\ReplCommand;
use Phel\Run\Infrastructure\Command\RunCommand;
use Phel\Run\Infrastructure\Command\TestCommand;

final class ConsoleProvider extends AbstractProvider
{
    public const string COMMANDS = 'COMMANDS';

    public const string FACADE_FILESYSTEM = 'FACADE_FILESYSTEM';

    public const string TAG_COMMIT_HASH = 'TAG_COMMIT_HASH';

    public const string CURRENT_COMMIT = 'CURRENT_COMMIT';

    private const string PACKAGE_NAME = 'phel-lang/phel-lang';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFilesystemFacade($container);
        $this->addCommands($container);
        $this->addVersionInfo($container);
    }

    private function addFilesystemFacade(Container $container): void
    {
        $container->set(
            self::FACADE_FILESYSTEM,
            static fn (Container $container) => $container->getLocator()->get(FilesystemFacade::class),
        );
    }

    private function addCommands(Container $container): void
    {
        $container->set(self::COMMANDS, static fn (): array => [
            new ExportCommand(),
            new FormatCommand(),
            new NsCommand(),
            new ReplCommand(),
            new EvalCommand(),
            new RunCommand(),
            new TestCommand(),
            new DocCommand(),
            new BuildCommand(),
            new DoctorCommand(),
        ]);
    }

    private function addVersionInfo(Container $container): void
    {
        $container->set(self::TAG_COMMIT_HASH, $this->resolveTagCommitHash(...));

        $container->set(self::CURRENT_COMMIT, $this->resolveCurrentCommit(...));
    }

    private function resolveTagCommitHash(): string
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

    private function resolveCurrentCommit(): string
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
}
