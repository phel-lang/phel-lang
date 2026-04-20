<?php

declare(strict_types=1);

namespace Phel\Console;

use Composer\InstalledVersions;
use Gacela\Console\Infrastructure\Command\CacheWarmCommand;
use Gacela\Console\Infrastructure\Command\DebugContainerCommand;
use Gacela\Console\Infrastructure\Command\DebugDependenciesCommand;
use Gacela\Console\Infrastructure\Command\DebugModulesCommand;
use Gacela\Console\Infrastructure\Command\ListModulesCommand;
use Gacela\Console\Infrastructure\Command\ProfileReportCommand;
use Gacela\Console\Infrastructure\Command\ValidateConfigCommand;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Api\Infrastructure\Command\AnalyzeCommand;
use Phel\Api\Infrastructure\Command\ApiDaemonCommand;
use Phel\Api\Infrastructure\Command\DocCommand;
use Phel\Api\Infrastructure\Command\IndexCommand;
use Phel\Build\Infrastructure\Command\BuildCommand;
use Phel\Build\Infrastructure\Command\CacheClearCommand;
use Phel\Console\Application\VersionFinder;
use Phel\Filesystem\FilesystemFacade;
use Phel\Formatter\Infrastructure\Command\FormatCommand;
use Phel\Interop\Infrastructure\Command\ExportCommand;
use Phel\Lint\Infrastructure\Command\LintCommand;
use Phel\Lsp\Infrastructure\Command\LspCommand;
use Phel\Nrepl\Infrastructure\Command\NreplCommand;
use Phel\Run\Infrastructure\Command\AgentInstallCommand;
use Phel\Run\Infrastructure\Command\DoctorCommand;
use Phel\Run\Infrastructure\Command\EvalCommand;
use Phel\Run\Infrastructure\Command\InitCommand;
use Phel\Run\Infrastructure\Command\NsCommand;
use Phel\Run\Infrastructure\Command\ReplCommand;
use Phel\Run\Infrastructure\Command\RunCommand;
use Phel\Run\Infrastructure\Command\TestCommand;
use Phel\Watch\Infrastructure\Command\WatchCommand;

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
        return [
            new InitCommand(),
            new AgentInstallCommand(),
            new ExportCommand(),
            new FormatCommand(),
            new NsCommand(),
            new ReplCommand(),
            new EvalCommand(),
            new RunCommand(),
            new TestCommand(),
            new DocCommand(),
            new AnalyzeCommand(),
            new IndexCommand(),
            new ApiDaemonCommand(),
            new BuildCommand(),
            new CacheClearCommand(),
            new CacheWarmCommand(),
            new DebugContainerCommand(),
            new DebugDependenciesCommand(),
            new DebugModulesCommand(),
            new ListModulesCommand(),
            new ProfileReportCommand(),
            new ValidateConfigCommand(),
            new DoctorCommand(),
            new NreplCommand(),
            new LintCommand(),
            new LspCommand(),
            new WatchCommand(),
        ];
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
}
