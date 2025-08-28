<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use Phel\Api\Infrastructure\Command\DocCommand;
use Phel\Build\Infrastructure\Command\BuildCommand;
use Phel\Console\Application\VersionFinder;
use Phel\Filesystem\FilesystemFacade;
use Phel\Formatter\Infrastructure\Command\FormatCommand;
use Phel\Interop\Infrastructure\Command\ExportCommand;
use Phel\Run\Infrastructure\Command\DoctorCommand;
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
            new RunCommand(),
            new TestCommand(),
            new DocCommand(),
            new BuildCommand(),
            new DoctorCommand(),
        ]);
    }

    private function addVersionInfo(Container $container): void
    {
        $container->set(self::TAG_COMMIT_HASH, static function (): string {
            $output = [];
            @exec('git rev-list -n 1 ' . VersionFinder::LATEST_VERSION . ' 2>/dev/null', $output);
            return $output[0] ?? '';
        });

        $container->set(self::CURRENT_COMMIT, static function (): string {
            $output = [];
            @exec('git rev-parse --verify HEAD 2>/dev/null', $output);
            return $output[0] ?? '';
        });
    }
}
