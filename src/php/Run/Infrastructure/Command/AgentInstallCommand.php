<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use FilesystemIterator;
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
use function is_dir;
use function is_file;
use function preg_replace;
use function rtrim;
use function sprintf;
use function trim;

final class AgentInstallCommand extends Command
{
    private const string ARG_PLATFORM = 'platform';

    private const string OPT_ALL = 'all';

    private const string OPT_WITH_DOCS = 'with-docs';

    private const string OPT_FORCE = 'force';

    private const string OPT_DRY_RUN = 'dry-run';

    private const string AGENTS_DIR = '.agents';

    private const string VERSION_FILE = 'VERSION';

    private const string BACKUP_SUFFIX = '.pre-phel.bak';

    private const string STAMP_PATTERN = '/\n?<!-- phel-agents v[^>]*-->\s*$/';

    /** @var array<string, array{source: string, target: string}> */
    private const array PLATFORMS = [
        'claude' => [
            'source' => 'skills/claude/phel-lang/SKILL.md',
            'target' => '.claude/skills/phel-lang/SKILL.md',
        ],
        'cursor' => [
            'source' => 'skills/cursor/phel.mdc',
            'target' => '.cursor/rules/phel.mdc',
        ],
        'codex' => [
            'source' => 'skills/codex/AGENTS.md',
            'target' => 'AGENTS.md',
        ],
        'gemini' => [
            'source' => 'skills/gemini/GEMINI.md',
            'target' => 'GEMINI.md',
        ],
        'copilot' => [
            'source' => 'skills/copilot/copilot-instructions.md',
            'target' => '.github/copilot-instructions.md',
        ],
        'aider' => [
            'source' => 'skills/aider/CONVENTIONS.md',
            'target' => 'CONVENTIONS.md',
        ],
    ];

    protected function configure(): void
    {
        $this->setName('agent-install')
            ->setDescription('Install agent skill files (Claude, Cursor, Codex, Gemini, Copilot, Aider) into the current project')
            ->addArgument(
                self::ARG_PLATFORM,
                InputArgument::OPTIONAL,
                sprintf('Platform: %s', implode(', ', array_keys(self::PLATFORMS))),
            )
            ->addOption(self::OPT_ALL, null, InputOption::VALUE_NONE, 'Install all supported platforms')
            ->addOption(self::OPT_WITH_DOCS, null, InputOption::VALUE_NONE, 'Also copy the bundled agent docs tree to .agents/')
            ->addOption(self::OPT_FORCE, null, InputOption::VALUE_NONE, 'Overwrite existing files (default: backup to ' . self::BACKUP_SUFFIX . ')')
            ->addOption(self::OPT_DRY_RUN, null, InputOption::VALUE_NONE, 'Print what would be written without changing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $platforms = $this->selectPlatforms($input, $output);
        if ($platforms === null) {
            return Command::INVALID;
        }

        $sourceRoot = $this->agentsRoot();
        $projectRoot = (string) getcwd();
        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);
        $force = (bool) $input->getOption(self::OPT_FORCE);

        foreach ($platforms as $platform) {
            $this->installPlatform($output, $sourceRoot, $projectRoot, $platform, $force, $dryRun);
        }

        if ((bool) $input->getOption(self::OPT_WITH_DOCS)) {
            $this->copyDocs($output, $sourceRoot, $projectRoot, $force, $dryRun);
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<string>|null list of platform keys, or null on invalid input
     */
    private function selectPlatforms(InputInterface $input, OutputInterface $output): ?array
    {
        $platform = $input->getArgument(self::ARG_PLATFORM);
        $installAll = (bool) $input->getOption(self::OPT_ALL);

        if ($installAll) {
            return array_keys(self::PLATFORMS);
        }

        if ($platform === null) {
            $output->writeln('<error>Provide a platform or use --all</error>');
            $output->writeln(sprintf('Platforms: %s', implode(', ', array_keys(self::PLATFORMS))));
            return null;
        }

        if (!isset(self::PLATFORMS[$platform])) {
            $output->writeln(sprintf('<error>Unknown platform: %s</error>', $platform));
            return null;
        }

        return [$platform];
    }

    private function installPlatform(
        OutputInterface $output,
        string $sourceRoot,
        string $projectRoot,
        string $platform,
        bool $force,
        bool $dryRun,
    ): void {
        $spec = self::PLATFORMS[$platform];
        $src = $sourceRoot . '/' . $spec['source'];
        $dst = $projectRoot . '/' . $spec['target'];

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
        file_put_contents($dst, $this->stampVersion($contents, $sourceRoot));
        $output->writeln(sprintf('<info>Installed</info> %s skill: %s', $platform, $dst));
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

    private function stampVersion(string $contents, string $sourceRoot): string
    {
        $versionFile = $sourceRoot . '/' . self::VERSION_FILE;
        if (!is_file($versionFile)) {
            return $contents;
        }

        $version = trim((string) file_get_contents($versionFile));
        if ($version === '') {
            return $contents;
        }

        $stripped = (string) preg_replace(self::STAMP_PATTERN, '', $contents);
        return rtrim($stripped) . sprintf("\n\n<!-- phel-agents v%s -->\n", $version);
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
