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
use function is_dir;
use function is_file;
use function sprintf;

final class AgentInstallCommand extends Command
{
    private const string ARG_PLATFORM = 'platform';

    private const string OPT_ALL = 'all';

    private const string OPT_WITH_DOCS = 'with-docs';

    private const string OPT_FORCE = 'force';

    private const string OPT_DRY_RUN = 'dry-run';

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
            ->addOption(self::OPT_WITH_DOCS, null, InputOption::VALUE_NONE, 'Also copy the .agents/ docs tree')
            ->addOption(self::OPT_FORCE, null, InputOption::VALUE_NONE, 'Overwrite existing files (default: backup to .pre-phel.bak)')
            ->addOption(self::OPT_DRY_RUN, null, InputOption::VALUE_NONE, 'Print what would be written without changing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $platform = $input->getArgument(self::ARG_PLATFORM);
        $installAll = (bool) $input->getOption(self::OPT_ALL);

        if ($platform === null && !$installAll) {
            $output->writeln('<error>Provide a platform or use --all</error>');
            $output->writeln(sprintf('Platforms: %s', implode(', ', array_keys(self::PLATFORMS))));
            return Command::INVALID;
        }

        $platforms = $installAll ? array_keys(self::PLATFORMS) : [$platform];
        $sourceRoot = $this->agentsRoot();
        $projectRoot = (string) getcwd();
        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);
        $force = (bool) $input->getOption(self::OPT_FORCE);

        foreach ($platforms as $p) {
            if (!isset(self::PLATFORMS[$p])) {
                $output->writeln(sprintf('<error>Unknown platform: %s</error>', $p));
                return Command::INVALID;
            }

            $this->installPlatform($output, $sourceRoot, $projectRoot, $p, $force, $dryRun);
        }

        if ((bool) $input->getOption(self::OPT_WITH_DOCS)) {
            $this->copyDocs($output, dirname($sourceRoot), $projectRoot, $force, $dryRun);
        }

        return Command::SUCCESS;
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

        $dstDir = dirname($dst);
        if (!is_dir($dstDir) && !mkdir($dstDir, 0o755, true) && !is_dir($dstDir)) {
            throw new RuntimeException(sprintf('Cannot create directory: %s', $dstDir));
        }

        if (is_file($dst) && !$force) {
            $backup = $dst . '.pre-phel.bak';
            copy($dst, $backup);
            $output->writeln(sprintf('Backed up existing -> %s', $backup));
        }

        copy($src, $dst);
        $output->writeln(sprintf('<info>Installed</info> %s skill: %s', $platform, $dst));
    }

    private function copyDocs(
        OutputInterface $output,
        string $agentsRoot,
        string $projectRoot,
        bool $force,
        bool $dryRun,
    ): void {
        $dst = $projectRoot . '/.agents';
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
        if (!is_dir($dst) && !mkdir($dst, 0o755, true) && !is_dir($dst)) {
            throw new RuntimeException(sprintf('Cannot create directory: %s', $dst));
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target = $dst . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0o755, true) && !is_dir($target)) {
                    throw new RuntimeException(sprintf('Cannot create directory: %s', $target));
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function agentsRoot(): string
    {
        foreach ([5, 4, 6] as $levels) {
            $candidate = dirname(__DIR__, $levels) . '/.agents';
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Cannot locate bundled .agents/ directory. '
            . 'The .agents/ tree is not shipped inside phel.phar; install phel-lang via '
            . 'Composer (composer require phel-lang/phel-lang) and run agent-install from '
            . './vendor/bin/phel instead.',
        );
    }
}
