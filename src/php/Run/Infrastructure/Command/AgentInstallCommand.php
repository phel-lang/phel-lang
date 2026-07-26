<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Phel\Run\Application\Agent\AgentInstaller;
use Phel\Run\Application\Agent\AgentPlatformDetector;
use Phel\Run\Domain\Agent\AgentPlatform;
use Phel\Run\Domain\Agent\AgentPlatformRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function implode;
use function is_file;
use function is_string;
use function sprintf;

/**
 * @internal
 */
final class AgentInstallCommand extends Command
{
    private const string ARG_PLATFORM = 'platform';

    private const string OPT_ALL = 'all';

    private const string OPT_AUTO = 'auto';

    private const string OPT_NO_DOCS = 'no-docs';

    private const string OPT_WITH_EXAMPLES = 'with-examples';

    private const string OPT_FORCE = 'force';

    private const string OPT_DRY_RUN = 'dry-run';

    private const string OPT_UNINSTALL = 'uninstall';

    private const string OPT_CHECK = 'check';

    private readonly AgentPlatformRegistry $registry;

    private readonly AgentInstaller $installer;

    public function __construct()
    {
        $this->registry = new AgentPlatformRegistry();
        $this->installer = new AgentInstaller();
        parent::__construct();
    }

    protected function configure(): void
    {
        $platformList = implode(', ', $this->registry->keys());

        $this->setName('agent-install')
            ->setDescription('Install agent skill files (Claude, Cursor, Codex, Gemini, Copilot, Aider) into the current project')
            ->setHelp(<<<HELP
Copies a per-platform skill file plus a shared <comment>.agents/</comment> docs tree
(rules, recipes, quick-syntax reference) into the current project. Example
projects are excluded by default; pass <comment>--with-examples</comment> to include them.

The docs tree syncs incrementally: re-running writes only what is new or has
changed upstream, and leaves any file you edited since the last install exactly
as it is. <comment>--force</comment> overwrites those too, backing each one up first.

<info>Common uses:</info>
  <comment>phel agent-install --auto</comment>             Install for agents detected in this project
  <comment>phel agent-install claude</comment>             Install only the Claude skill + docs
  <comment>phel agent-install --all --force</comment>      Reinstall every platform, overwriting
  <comment>phel agent-install claude --uninstall</comment> Remove the Claude skill (restore backup)
  <comment>phel agent-install --check</comment>            Report docs version drift; exit 1 when stale

Existing skill files are backed up to <comment>.pre-phel.bak</comment> unless <comment>--force</comment> is used.
HELP)
            ->addArgument(self::ARG_PLATFORM, InputArgument::OPTIONAL, sprintf('Platform: %s', $platformList))
            ->addOption(self::OPT_ALL, null, InputOption::VALUE_NONE, 'Install for every supported platform')
            ->addOption(self::OPT_AUTO, null, InputOption::VALUE_NONE, 'Install only for agents detected in this project (.claude/, .cursor/, AGENTS.md, ...)')
            ->addOption(self::OPT_NO_DOCS, null, InputOption::VALUE_NONE, 'Skip copying the .agents/ docs tree (copied by default)')
            ->addOption(self::OPT_WITH_EXAMPLES, null, InputOption::VALUE_NONE, 'Include example projects in .agents/examples/ (excluded by default)')
            ->addOption(self::OPT_FORCE, null, InputOption::VALUE_NONE, 'Overwrite the skill file without a ' . AgentInstaller::BACKUP_SUFFIX . ' backup, and overwrite locally modified docs (backing those up)')
            ->addOption(self::OPT_DRY_RUN, null, InputOption::VALUE_NONE, 'Print what would be written without changing files')
            ->addOption(self::OPT_UNINSTALL, null, InputOption::VALUE_NONE, 'Remove installed skill file(s); restores ' . AgentInstaller::BACKUP_SUFFIX . ' if present')
            ->addOption(self::OPT_CHECK, null, InputOption::VALUE_NONE, 'Report the installed docs version against the bundled one; exit 1 when they differ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceRoot = $this->installer->locateSourceRoot();
        $projectRoot = (string) getcwd();

        if ((bool) $input->getOption(self::OPT_CHECK)) {
            return $this->renderCheck($output, $sourceRoot, $projectRoot);
        }

        $platforms = $this->selectPlatforms($input, $output);
        if ($platforms === null) {
            return Command::INVALID;
        }

        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);
        $force = (bool) $input->getOption(self::OPT_FORCE);
        $copyDocs = !(bool) $input->getOption(self::OPT_NO_DOCS);
        $withExamples = (bool) $input->getOption(self::OPT_WITH_EXAMPLES);

        if (!$copyDocs && $withExamples) {
            $output->writeln('<comment>--with-examples ignored: --no-docs disables the docs tree entirely.</comment>');
            $withExamples = false;
        }

        if ((bool) $input->getOption(self::OPT_UNINSTALL)) {
            foreach ($platforms as $platformKey) {
                $this->renderUninstall($output, $projectRoot, $this->registry->get($platformKey), $dryRun);
            }

            if ($copyDocs) {
                $this->renderRemoveDocs($output, $projectRoot, $dryRun);
            }

            return Command::SUCCESS;
        }

        foreach ($platforms as $platformKey) {
            $this->renderInstall($output, $sourceRoot, $projectRoot, $this->registry->get($platformKey), $force, $dryRun);
        }

        if ($copyDocs) {
            $this->renderSyncDocs($output, $sourceRoot, $projectRoot, $force, $dryRun, $withExamples);
        }

        if (!$dryRun) {
            $output->writeln('');
            $output->writeln('<info>Done.</info>');

            ShellCompletionTip::writeTo($output);
        }

        return Command::SUCCESS;
    }

    private function renderInstall(OutputInterface $output, string $sourceRoot, string $projectRoot, AgentPlatform $platform, bool $force, bool $dryRun): void
    {
        $dst = $projectRoot . '/' . $platform->target;

        if ($dryRun) {
            $output->writeln(sprintf('[dry-run] %s -> %s', $sourceRoot . '/' . $platform->source, $dst));
            return;
        }

        if ($this->installer->installSkill($sourceRoot, $projectRoot, $platform, $force)) {
            $output->writeln(sprintf('Backed up existing -> %s', $dst . AgentInstaller::BACKUP_SUFFIX));
        }

        $output->writeln(sprintf('<info>Installed</info> %s skill: %s', $platform->key, $dst));
    }

    private function renderUninstall(OutputInterface $output, string $projectRoot, AgentPlatform $platform, bool $dryRun): void
    {
        $dst = $projectRoot . '/' . $platform->target;

        if ($dryRun) {
            if (!is_file($dst)) {
                $output->writeln(sprintf('  %-8s not installed; skip', $platform->key));
                return;
            }

            $output->writeln(sprintf('[dry-run] remove %s', $dst));
            if (is_file($dst . AgentInstaller::BACKUP_SUFFIX)) {
                $output->writeln(sprintf('[dry-run] restore %s -> %s', $dst . AgentInstaller::BACKUP_SUFFIX, $dst));
            }

            return;
        }

        match ($this->installer->uninstallSkill($projectRoot, $platform)) {
            AgentInstaller::UNINSTALL_RESTORED => $output->writeln(sprintf('<info>Restored backup</info> %s', $dst)),
            AgentInstaller::UNINSTALL_REMOVED => $output->writeln(sprintf('<info>Removed</info> %s skill: %s', $platform->key, $dst)),
            default => $output->writeln(sprintf('  %-8s not installed; skip', $platform->key)),
        };
    }

    /**
     * Report install state and docs version drift without writing anything, so
     * CI can gate on "your `.agents/` is behind the phel-lang you installed".
     */
    private function renderCheck(OutputInterface $output, string $sourceRoot, string $projectRoot): int
    {
        $bundled = $this->installer->bundledDocsVersion($sourceRoot);
        $installed = $this->installer->installedDocsVersion($projectRoot);

        foreach ($this->registry->keys() as $key) {
            $target = $this->registry->get($key)->target;
            $output->writeln(sprintf(
                '  %-8s %s %s',
                $key,
                is_file($projectRoot . '/' . $target) ? '<info>installed</info>' : 'absent   ',
                $target,
            ));
        }

        if ($installed === null) {
            $output->writeln(sprintf('<comment>No .agents/ docs installed (bundled: %s).</comment>', $bundled));
            $output->writeln('Run <comment>phel agent-install --auto</comment> to install them.');
            return Command::FAILURE;
        }

        if ($installed !== $bundled) {
            $output->writeln(sprintf('<comment>Docs are stale: installed %s, bundled %s.</comment>', $installed, $bundled));
            $output->writeln('Run <comment>phel agent-install</comment> again to sync the changed files.');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Docs are up to date</info> (%s).', $installed));

        return Command::SUCCESS;
    }

    private function renderSyncDocs(OutputInterface $output, string $sourceRoot, string $projectRoot, bool $force, bool $dryRun, bool $withExamples): void
    {
        $dst = $projectRoot . '/' . AgentInstaller::AGENTS_DIR;
        $installedVersion = $this->installer->installedDocsVersion($projectRoot);
        $bundledVersion = $this->installer->bundledDocsVersion($sourceRoot);
        $prefix = $dryRun ? '[dry-run] ' : '';

        if ($installedVersion !== null && $installedVersion !== $bundledVersion) {
            $output->writeln(sprintf('Docs %s -> <info>%s</info>', $installedVersion, $bundledVersion));
        }

        $result = $this->installer->syncDocs($sourceRoot, $projectRoot, $force, $withExamples, $dryRun);

        $output->writeln(sprintf(
            '%s<info>Docs tree</info> %s: %d new, %d updated, %d unchanged',
            $prefix,
            $dst,
            count($result->created),
            count($result->updated) + count($result->backedUp),
            count($result->unchanged),
        ));

        foreach ($result->backedUp as $relative) {
            $output->writeln(sprintf(
                '%sBacked up your %s -> %s',
                $prefix,
                $relative,
                $relative . AgentInstaller::BACKUP_SUFFIX,
            ));
        }

        if ($result->skipped !== []) {
            $output->writeln(sprintf(
                '<comment>Kept %d locally modified file(s): %s</comment>',
                count($result->skipped),
                implode(', ', $result->skipped),
            ));
            $output->writeln('<comment>Pass --force to overwrite them (your version is backed up first).</comment>');
        }

        if (!$withExamples) {
            $output->writeln('<comment>Skipped examples/ (pass --with-examples to include sample apps).</comment>');
        }
    }

    private function renderRemoveDocs(OutputInterface $output, string $projectRoot, bool $dryRun): void
    {
        $dst = $projectRoot . '/' . AgentInstaller::AGENTS_DIR;

        if ($dryRun) {
            if (is_dir($dst)) {
                $output->writeln(sprintf('[dry-run] remove %s', $dst));
            }

            return;
        }

        if ($this->installer->removeDocs($projectRoot)) {
            $output->writeln(sprintf('<info>Removed docs tree</info> %s', $dst));
        }
    }

    /**
     * @return list<string>|null platform keys, or null on invalid input
     */
    private function selectPlatforms(InputInterface $input, OutputInterface $output): ?array
    {
        if ((bool) $input->getOption(self::OPT_AUTO)) {
            $detected = $this->detectAgents();
            if ($detected === []) {
                $output->writeln('<comment>No agent traces detected; nothing to install.</comment>');
                $output->writeln(sprintf('Pass a platform (%s) or use <comment>--all</comment>.', implode(', ', $this->registry->keys())));
                return null;
            }

            $output->writeln(sprintf('Detected: <info>%s</info>', implode(', ', $detected)));
            return $detected;
        }

        if ((bool) $input->getOption(self::OPT_ALL)) {
            return $this->registry->keys();
        }

        $platform = $input->getArgument(self::ARG_PLATFORM);
        if (!is_string($platform)) {
            $detected = $this->detectAgents();
            if ($detected !== []) {
                $output->writeln(sprintf('Detected installed agents: <info>%s</info>', implode(', ', $detected)));
                $output->writeln('Run <comment>agent-install --auto</comment> to install for them, or <comment>--all</comment> for every platform.');
                return null;
            }

            $output->writeln('<error>Provide a platform or use --all / --auto</error>');
            $output->writeln(sprintf('Platforms: %s', implode(', ', $this->registry->keys())));
            return null;
        }

        if (!$this->registry->has($platform)) {
            $output->writeln(sprintf('<error>Unknown platform: %s</error>', $platform));
            $output->writeln(sprintf('Available: %s', implode(', ', $this->registry->keys())));
            return null;
        }

        return [$platform];
    }

    /**
     * @return list<string>
     */
    private function detectAgents(): array
    {
        return new AgentPlatformDetector($this->registry)->detect((string) getcwd());
    }
}
