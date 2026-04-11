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

    private const string OPT_MINIMAL = 'minimal';

    private const string OPT_FORCE = 'force';

    private const string OPT_DRY_RUN = 'dry-run';

    private const string OPT_NO_GITIGNORE = 'no-gitignore';

    private const string OPT_NO_TESTS = 'no-tests';

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
                self::OPT_MINIMAL,
                'm',
                InputOption::VALUE_NONE,
                'Use root layout: single main.phel at the project root, no subdirectories',
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
            )
            ->addOption(
                self::OPT_NO_GITIGNORE,
                null,
                InputOption::VALUE_NONE,
                'Skip .gitignore file creation',
            )
            ->addOption(
                self::OPT_NO_TESTS,
                null,
                InputOption::VALUE_NONE,
                'Skip generating a matching test file',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectName = (string) $input->getArgument(self::ARG_PROJECT_NAME);
        $layout = $this->resolveLayout($input);
        $force = (bool) $input->getOption(self::OPT_FORCE);
        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);
        $noGitignore = (bool) $input->getOption(self::OPT_NO_GITIGNORE);
        $noTests = (bool) $input->getOption(self::OPT_NO_TESTS);

        $cwd = getcwd();
        if ($cwd === false) {
            $output->writeln('<error>Unable to determine current working directory.</error>');

            return Command::FAILURE;
        }

        if ($dryRun) {
            $output->writeln('<comment>Dry run mode - no files will be created</comment>');
            $output->writeln('');
        }

        $namespace = $this->namespaceNormalizer->normalize($projectName, $layout);
        $coreFilename = $this->coreFilename($layout);
        $testFilename = $this->testFilename($layout);

        if (!$this->createDirectories($cwd, $this->directoriesFor($layout), $output, $dryRun)) {
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
            $cwd . '/' . $coreFilename,
            $this->templateGenerator->generateCoreFile($namespace),
            $coreFilename,
            $output,
            $force,
            $dryRun,
        )) {
            return Command::FAILURE;
        }

        if (!$noTests && !$this->createFile(
            $cwd . '/' . $testFilename,
            $this->templateGenerator->generateTestFile($namespace),
            $testFilename,
            $output,
            $force,
            $dryRun,
        )) {
            return Command::FAILURE;
        }

        if (!$noGitignore) {
            $this->createFile(
                $cwd . '/.gitignore',
                $this->templateGenerator->generateGitignore($layout),
                '.gitignore',
                $output,
                $force,
                $dryRun,
                skipExistsMessage: true,
            );
        }

        if (!$dryRun) {
            $this->printNextSteps($output, $coreFilename, $noTests);
        }

        return Command::SUCCESS;
    }

    private function resolveLayout(InputInterface $input): ProjectLayout
    {
        if ((bool) $input->getOption(self::OPT_MINIMAL)) {
            return ProjectLayout::Root;
        }

        if ((bool) $input->getOption(self::OPT_FLAT)) {
            return ProjectLayout::Flat;
        }

        return ProjectLayout::Conventional;
    }

    private function coreFilename(ProjectLayout $layout): string
    {
        return match ($layout) {
            ProjectLayout::Root => 'main.phel',
            default => $layout->getSrcDir() . '/core.phel',
        };
    }

    private function testFilename(ProjectLayout $layout): string
    {
        return match ($layout) {
            ProjectLayout::Root => 'main_test.phel',
            default => $layout->getTestDir() . '/core_test.phel',
        };
    }

    /**
     * @return list<string>
     */
    private function directoriesFor(ProjectLayout $layout): array
    {
        if ($layout === ProjectLayout::Root) {
            return [];
        }

        return [$layout->getSrcDir(), $layout->getTestDir(), self::DIR_OUT];
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

    private function printNextSteps(OutputInterface $output, string $coreFilename, bool $noTests): void
    {
        $output->writeln('');
        $output->writeln('<info>Phel project initialized successfully!</info>');
        $output->writeln('');
        $output->writeln('Next steps:');

        $step = 1;
        $output->writeln(sprintf('  %d. Run your code:  <comment>./vendor/bin/phel run %s</comment>', $step++, $coreFilename));
        if (!$noTests) {
            $output->writeln(sprintf('  %d. Run the tests: <comment>./vendor/bin/phel test</comment>', $step++));
        }

        $output->writeln(sprintf('  %d. Start the REPL: <comment>./vendor/bin/phel repl</comment>', $step++));
        $output->writeln(sprintf('  %d. Build for production: <comment>./vendor/bin/phel build</comment>', $step));
    }
}
