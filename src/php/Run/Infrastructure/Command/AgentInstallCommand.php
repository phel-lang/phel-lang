<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use FilesystemIterator;
use Phel\Run\Application\Agent\AgentInstallStatusInspector;
use Phel\Run\Application\Agent\AgentPlatformDetector;
use Phel\Run\Application\Agent\AgentVersionStamper;
use Phel\Run\Domain\Agent\AgentPlatform;
use Phel\Run\Domain\Agent\AgentPlatformRegistry;
use Phel\Run\Domain\Agent\AgentPlatformStatus;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_file;
use function rename;
use function rmdir;
use function sprintf;
use function str_repeat;
use function unlink;

final class AgentInstallCommand extends Command
{
    private const string ARG_PLATFORM = 'platform';

    private const string OPT_ALL = 'all';

    private const string OPT_NO_DOCS = 'no-docs';

    private const string OPT_FORCE = 'force';

    private const string OPT_DRY_RUN = 'dry-run';

    private const string OPT_CHECK = 'check';

    private const string OPT_LIST = 'list';

    private const string OPT_UNINSTALL = 'uninstall';

    private const string OPT_AUTO = 'auto';

    private const string AGENTS_DIR = '.agents';

    private const string BACKUP_SUFFIX = '.pre-phel.bak';

    private readonly AgentPlatformRegistry $registry;

    public function __construct()
    {
        $this->registry = new AgentPlatformRegistry();
        parent::__construct();
    }

    protected function configure(): void
    {
        $platformList = implode(', ', $this->registry->keys());

        $this->setName('agent-install')
            ->setDescription('Install agent skill files (Claude, Cursor, Codex, Gemini, Copilot, Aider) into the current project')
            ->addArgument(
                self::ARG_PLATFORM,
                InputArgument::OPTIONAL,
                sprintf('Platform: %s', $platformList),
            )
            ->addOption(self::OPT_ALL, null, InputOption::VALUE_NONE, 'Install all supported platforms')
            ->addOption(self::OPT_NO_DOCS, null, InputOption::VALUE_NONE, 'Skip copying the bundled agent docs tree to .agents/ (copied by default)')
            ->addOption(self::OPT_FORCE, null, InputOption::VALUE_NONE, 'Overwrite existing files (default: backup to ' . self::BACKUP_SUFFIX . ')')
            ->addOption(self::OPT_DRY_RUN, null, InputOption::VALUE_NONE, 'Print what would be written without changing files')
            ->addOption(self::OPT_CHECK, null, InputOption::VALUE_NONE, 'Report install status and version drift per platform; exit 1 if any drift')
            ->addOption(self::OPT_LIST, null, InputOption::VALUE_NONE, 'List supported platforms with source template, install target, and current state')
            ->addOption(self::OPT_UNINSTALL, null, InputOption::VALUE_NONE, 'Remove installed skill file(s); restores ' . self::BACKUP_SUFFIX . ' if present')
            ->addOption(self::OPT_AUTO, null, InputOption::VALUE_NONE, 'Auto-detect agents already present in the project (.claude/, .cursor/, AGENTS.md, ...) and install for them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceRoot = $this->agentsRoot();
        $projectRoot = (string) getcwd();
        $stamper = new AgentVersionStamper($sourceRoot);

        if ((bool) $input->getOption(self::OPT_CHECK)) {
            return $this->runCheck($output, $projectRoot, $stamper);
        }

        if ((bool) $input->getOption(self::OPT_LIST)) {
            return $this->runList($output, $projectRoot, $stamper);
        }

        $platforms = $this->selectPlatforms($input, $output);
        if ($platforms === null) {
            return Command::INVALID;
        }

        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);
        $force = (bool) $input->getOption(self::OPT_FORCE);

        $copyDocs = !(bool) $input->getOption(self::OPT_NO_DOCS);

        if ((bool) $input->getOption(self::OPT_UNINSTALL)) {
            foreach ($platforms as $platformKey) {
                $this->uninstallPlatform($output, $projectRoot, $this->registry->get($platformKey), $dryRun);
            }

            if ($copyDocs) {
                $this->removeDocs($output, $projectRoot, $dryRun);
            }

            return Command::SUCCESS;
        }

        foreach ($platforms as $platformKey) {
            $this->installPlatform($output, $sourceRoot, $projectRoot, $this->registry->get($platformKey), $force, $dryRun, $stamper);
        }

        if ($copyDocs) {
            $this->copyDocs($output, $sourceRoot, $projectRoot, $force, $dryRun);
        }

        return Command::SUCCESS;
    }

    private function uninstallPlatform(OutputInterface $output, string $projectRoot, AgentPlatform $platform, bool $dryRun): void
    {
        $dst = $projectRoot . '/' . $platform->target;
        $backup = $dst . self::BACKUP_SUFFIX;

        if (!is_file($dst)) {
            $output->writeln(sprintf('  %-8s not installed; skip', $platform->key));
            return;
        }

        if ($dryRun) {
            $output->writeln(sprintf('[dry-run] remove %s', $dst));
            if (is_file($backup)) {
                $output->writeln(sprintf('[dry-run] restore %s -> %s', $backup, $dst));
            }

            return;
        }

        unlink($dst);
        if (is_file($backup)) {
            rename($backup, $dst);
            $output->writeln(sprintf('<info>Restored backup</info> %s', $dst));
            return;
        }

        $output->writeln(sprintf('<info>Removed</info> %s skill: %s', $platform->key, $dst));
    }

    private function removeDocs(OutputInterface $output, string $projectRoot, bool $dryRun): void
    {
        $dst = $projectRoot . '/' . self::AGENTS_DIR;
        if (!is_dir($dst)) {
            return;
        }

        if ($dryRun) {
            $output->writeln(sprintf('[dry-run] remove %s', $dst));
            return;
        }

        $this->recursiveRemove($dst);
        $output->writeln(sprintf('<info>Removed docs tree</info> %s', $dst));
    }

    private function recursiveRemove(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }

    private function runList(OutputInterface $output, string $projectRoot, AgentVersionStamper $stamper): int
    {
        $inspector = new AgentInstallStatusInspector($this->registry, $stamper);
        $statuses = $inspector->inspect($projectRoot);

        $output->writeln(sprintf('<info>Platform</info>  <info>Source</info>%s  <info>Target</info>%s  <info>State</info>', str_repeat(' ', 36), str_repeat(' ', 30)));
        foreach ($statuses as $status) {
            $output->writeln(sprintf(
                '  %-8s  %-40s  %-34s  %s',
                $status->platform->key,
                $status->platform->source,
                $status->platform->target,
                $this->stateLabel($status),
            ));
        }

        return Command::SUCCESS;
    }

    private function stateLabel(AgentPlatformStatus $status): string
    {
        return match ($status->state) {
            AgentPlatformStatus::CURRENT => sprintf('current (v%s)', $status->installedVersion ?? '?'),
            AgentPlatformStatus::STALE => sprintf('stale (v%s, current v%s)', $status->installedVersion ?? '?', $status->currentVersion),
            AgentPlatformStatus::UNSTAMPED => 'unstamped',
            AgentPlatformStatus::NOT_INSTALLED => 'not installed',
            default => $status->state,
        };
    }

    private function runCheck(OutputInterface $output, string $projectRoot, AgentVersionStamper $stamper): int
    {
        $inspector = new AgentInstallStatusInspector($this->registry, $stamper);
        $statuses = $inspector->inspect($projectRoot);

        $output->writeln(sprintf('phel-agents target version: <info>%s</info>', $stamper->currentVersion() ?? 'unknown'));
        $output->writeln('');

        $hasDrift = false;
        foreach ($statuses as $status) {
            $output->writeln($this->formatStatusLine($status));
            $hasDrift = $hasDrift || $status->isDrift();
        }

        return $hasDrift ? Command::FAILURE : Command::SUCCESS;
    }

    private function formatStatusLine(AgentPlatformStatus $status): string
    {
        $key = $status->platform->key;
        $installed = $status->installedVersion ?? '?';

        return match ($status->state) {
            AgentPlatformStatus::CURRENT => sprintf('  <info>✓</info> %-8s v%s', $key, $installed),
            AgentPlatformStatus::STALE => sprintf('  <comment>!</comment> %-8s v%s (current v%s) — run `agent-install %s --force` to refresh', $key, $installed, $status->currentVersion, $key),
            AgentPlatformStatus::UNSTAMPED => sprintf('  <comment>?</comment> %-8s file exists but has no version stamp — run `agent-install %s --force` to refresh', $key, $key),
            AgentPlatformStatus::NOT_INSTALLED => sprintf('    %-8s not installed', $key),
            default => sprintf('  %-8s unknown state: %s', $key, $status->state),
        };
    }

    /**
     * @return list<string>|null list of platform keys, or null on invalid input
     */
    private function selectPlatforms(InputInterface $input, OutputInterface $output): ?array
    {
        if ((bool) $input->getOption(self::OPT_AUTO)) {
            return $this->selectAutoDetected($output);
        }

        if ((bool) $input->getOption(self::OPT_ALL)) {
            return $this->registry->keys();
        }

        $platform = $input->getArgument(self::ARG_PLATFORM);
        if ($platform === null) {
            $this->reportMissingPlatform($output);
            return null;
        }

        if (!$this->registry->has($platform)) {
            $output->writeln(sprintf('<error>Unknown platform: %s</error>', $platform));
            return null;
        }

        return [$platform];
    }

    /**
     * @return list<string>|null
     */
    private function selectAutoDetected(OutputInterface $output): ?array
    {
        $detected = $this->detectAgents();
        if ($detected === []) {
            $output->writeln('<comment>No agent traces detected; nothing to install. Use --all or pass a platform.</comment>');
            return null;
        }

        $output->writeln(sprintf('Detected: <info>%s</info>', implode(', ', $detected)));
        return $detected;
    }

    private function reportMissingPlatform(OutputInterface $output): void
    {
        $detected = $this->detectAgents();
        if ($detected !== []) {
            $output->writeln(sprintf('Detected installed agents: <info>%s</info>', implode(', ', $detected)));
            $output->writeln('Run <comment>agent-install --auto</comment> to install skills for detected agents, or <comment>--all</comment> for every platform.');
            return;
        }

        $output->writeln('<error>Provide a platform or use --all</error>');
        $output->writeln(sprintf('Platforms: %s', implode(', ', $this->registry->keys())));
    }

    /**
     * @return list<string>
     */
    private function detectAgents(): array
    {
        return new AgentPlatformDetector($this->registry)->detect((string) getcwd());
    }

    private function installPlatform(
        OutputInterface $output,
        string $sourceRoot,
        string $projectRoot,
        AgentPlatform $platform,
        bool $force,
        bool $dryRun,
        AgentVersionStamper $stamper,
    ): void {
        $src = $sourceRoot . '/' . $platform->source;
        $dst = $projectRoot . '/' . $platform->target;

        if (!is_file($src)) {
            throw new RuntimeException(sprintf('Source skill file not found: %s', $src));
        }

        if ($dryRun) {
            $output->writeln(sprintf('[dry-run] %s -> %s', $src, $dst));
            return;
        }

        $this->ensureDir(dirname($dst));
        $this->backupIfExists($output, $dst, $force);

        $contents = (string) file_get_contents($src);
        file_put_contents($dst, $stamper->stamp($contents));
        $output->writeln(sprintf('<info>Installed</info> %s skill: %s', $platform->key, $dst));
    }

    private function backupIfExists(OutputInterface $output, string $dst, bool $force): void
    {
        if (!is_file($dst) || $force) {
            return;
        }

        $backup = $dst . self::BACKUP_SUFFIX;
        copy($dst, $backup);
        $output->writeln(sprintf('Backed up existing -> %s', $backup));
    }

    private function copyDocs(
        OutputInterface $output,
        string $agentsRoot,
        string $projectRoot,
        bool $force,
        bool $dryRun,
    ): void {
        $dst = $projectRoot . '/' . self::AGENTS_DIR;
        if ($dryRun) {
            $output->writeln(sprintf('[dry-run] copy %s -> %s', $agentsRoot, $dst));
            return;
        }

        if (is_dir($dst) && !$force) {
            $output->writeln('<comment>.agents/ already exists; skipping (use --force to overwrite)</comment>');
            return;
        }

        $this->recursiveCopy($agentsRoot, $dst);
        $output->writeln(sprintf('<info>Copied docs tree</info> -> %s', $dst));
    }

    private function recursiveCopy(string $src, string $dst): void
    {
        $this->ensureDir($dst);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target = $dst . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                $this->ensureDir($target);
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Cannot create directory: %s', $dir));
        }
    }

    private function agentsRoot(): string
    {
        foreach ([5, 4, 6] as $levels) {
            $candidate = dirname(__DIR__, $levels) . '/resources/agents';
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Cannot locate bundled resources/agents/ directory. '
            . 'The downstream agent docs tree is not shipped inside phel.phar; install phel-lang via '
            . 'Composer (composer require phel-lang/phel-lang) and run agent-install from '
            . './vendor/bin/phel instead.',
        );
    }
}
