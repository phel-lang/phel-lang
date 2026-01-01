<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Phel\Config\ProjectLayout;
use Phel\Run\Domain\Init\NamespaceNormalizer;
use Phel\Run\Domain\Init\ProjectTemplateGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

final class InitCommand extends Command
{
    private const string ARG_PROJECT_NAME = 'project-name';

    private const string OPT_FLAT = 'flat';

    private const string OPT_FORCE = 'force';

    private const string OPT_DRY_RUN = 'dry-run';

    private const string DIR_OUT = 'out';

    public function __construct(
        private readonly ProjectTemplateGenerator $templateGenerator = new ProjectTemplateGenerator(),
        private readonly NamespaceNormalizer $namespaceNormalizer = new NamespaceNormalizer(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('init')
            ->setDescription('Initialize a new Phel project with minimal configuration')
            ->addArgument(
                self::ARG_PROJECT_NAME,
                InputArgument::OPTIONAL,
                'The project/namespace name (e.g., my-app)',
                'app',
            )
            ->addOption(
                self::OPT_FLAT,
                'f',
                InputOption::VALUE_NONE,
                'Use flat directory layout (src/ instead of src/phel/)',
            )
            ->addOption(
                self::OPT_FORCE,
                null,
                InputOption::VALUE_NONE,
                'Overwrite existing files',
            )
            ->addOption(
                self::OPT_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Show what would be created without making changes',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectName = (string) $input->getArgument(self::ARG_PROJECT_NAME);
        $layout = $input->getOption(self::OPT_FLAT)
            ? ProjectLayout::Flat
            : ProjectLayout::Conventional;
        $force = (bool) $input->getOption(self::OPT_FORCE);
        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);

        $cwd = getcwd();
        if ($cwd === false) {
            $output->writeln('<error>Unable to determine current working directory.</error>');

            return Command::FAILURE;
        }

        if ($dryRun) {
            $output->writeln('<comment>Dry run mode - no files will be created</comment>');
            $output->writeln('');
        }

        $srcDir = $layout->getSrcDir();
        $testDir = $layout->getTestDir();
        $namespace = $this->namespaceNormalizer->normalize($projectName);

        if (!$this->createDirectories($cwd, [$srcDir, $testDir, self::DIR_OUT], $output, $dryRun)) {
            return Command::FAILURE;
        }

        if (!$this->createFile(
            $cwd . '/phel-config.php',
            $this->templateGenerator->generateConfig($namespace, $layout),
            'phel-config.php',
            $output,
            $force,
            $dryRun,
        )) {
            return Command::FAILURE;
        }

        if (!$this->createFile(
            $cwd . '/' . $srcDir . '/core.phel',
            $this->templateGenerator->generateCoreFile($namespace),
            $srcDir . '/core.phel',
            $output,
            $force,
            $dryRun,
        )) {
            return Command::FAILURE;
        }

        $this->createFile(
            $cwd . '/.gitignore',
            $this->templateGenerator->generateGitignore(),
            '.gitignore',
            $output,
            $force,
            $dryRun,
            skipExistsMessage: true,
        );

        if (!$dryRun) {
            $this->printNextSteps($output, $srcDir);
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $directories
     */
    private function createDirectories(
        string $basePath,
        array $directories,
        OutputInterface $output,
        bool $dryRun,
    ): bool {
        foreach ($directories as $dir) {
            $fullPath = $basePath . '/' . $dir;

            if (is_dir($fullPath)) {
                continue;
            }

            if ($dryRun) {
                $output->writeln(sprintf('<info>[DRY-RUN] Would create directory: %s</info>', $dir));

                continue;
            }

            if (!mkdir($fullPath, 0755, true)) {
                $output->writeln(sprintf('<error>Failed to create directory: %s</error>', $fullPath));

                return false;
            }

            if ($output->isVerbose()) {
                $output->writeln(sprintf('<info>Created directory: %s</info>', $dir));
            }
        }

        return true;
    }

    private function createFile(
        string $path,
        string $content,
        string $displayName,
        OutputInterface $output,
        bool $force,
        bool $dryRun,
        bool $skipExistsMessage = false,
    ): bool {
        $exists = file_exists($path);

        if ($exists && !$force) {
            if (!$skipExistsMessage) {
                $output->writeln(sprintf('<comment>%s already exists, skipping (use --force to overwrite)</comment>', $displayName));
            }

            return true;
        }

        if ($dryRun) {
            $action = $exists ? 'Would overwrite' : 'Would create';
            $output->writeln(sprintf('<info>[DRY-RUN] %s: %s</info>', $action, $displayName));

            return true;
        }

        if (file_put_contents($path, $content) === false) {
            $output->writeln(sprintf('<error>Failed to create %s</error>', $displayName));

            return false;
        }

        $action = $exists ? 'Overwrote' : 'Created';
        $output->writeln(sprintf('<info>%s %s</info>', $action, $displayName));

        return true;
    }

    private function printNextSteps(OutputInterface $output, string $srcDir): void
    {
        $output->writeln('');
        $output->writeln('<info>Phel project initialized successfully!</info>');
        $output->writeln('');
        $output->writeln('Next steps:');
        $output->writeln(sprintf('  1. Run your code:  <comment>./vendor/bin/phel run %s/core.phel</comment>', $srcDir));
        $output->writeln('  2. Start the REPL: <comment>./vendor/bin/phel repl</comment>');
        $output->writeln('  3. Build for production: <comment>./vendor/bin/phel build</comment>');
    }
}
