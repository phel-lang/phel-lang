<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Phel\Config\ProjectLayout;
use Phel\Run\Domain\Init\NamespaceNormalizer;
use Phel\Run\Domain\Init\ProjectTemplateGenerator;
use Phel\Run\Domain\Init\ProjectTemplateScaffolder;
use Phel\Shared\ScalarCoercion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function dirname;
use function is_dir;
use function mkdir;
use function sprintf;

final class InitCommand extends Command
{
    private const string ARG_PROJECT_NAME = 'project-name';

    private const string OPT_NESTED = 'nested';

    private const string OPT_MINIMAL = 'minimal';

    private const string OPT_FORCE = 'force';

    private const string OPT_DRY_RUN = 'dry-run';

    private const string OPT_NO_GITIGNORE = 'no-gitignore';

    private const string OPT_NO_TESTS = 'no-tests';

    private const string OPT_TEMPLATE = 'template';

    private const string OPT_LIST_TEMPLATES = 'list-templates';

    private const string DIR_OUT = 'out';

    public function __construct(
        private readonly ProjectTemplateGenerator $templateGenerator = new ProjectTemplateGenerator(),
        private readonly NamespaceNormalizer $namespaceNormalizer = new NamespaceNormalizer(),
        private readonly ProjectTemplateScaffolder $templateScaffolder = new ProjectTemplateScaffolder(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('init')
            ->setDescription('Initialize a new Phel project with minimal configuration')
            ->setHelp(<<<'HELP'
Scaffolds phel-config.php, a main namespace, a test, and .gitignore in the
current directory. Use --template to start from a bundled example.

<info>Examples:</info>
  <comment>phel init my-app</comment>                Flat layout in the current directory
  <comment>phel init my-app --template=http-json-api</comment>   Scaffold an example
HELP)
            ->addArgument(
                self::ARG_PROJECT_NAME,
                InputArgument::OPTIONAL,
                'The project/namespace name (e.g., my-app)',
                'app',
            )
            ->addOption(
                self::OPT_NESTED,
                null,
                InputOption::VALUE_NONE,
                'Use nested directory layout (src/phel/ instead of src/)',
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
            )
            ->addOption(
                self::OPT_TEMPLATE,
                't',
                InputOption::VALUE_OPTIONAL,
                'Scaffold from a bundled example template (e.g. http-json-api); omit the value to list templates',
                false,
            )
            ->addOption(
                self::OPT_LIST_TEMPLATES,
                null,
                InputOption::VALUE_NONE,
                'List the available project templates and exit',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectName = ScalarCoercion::toString($input->getArgument(self::ARG_PROJECT_NAME));
        $layout = $this->resolveLayout($input);
        $force = (bool) $input->getOption(self::OPT_FORCE);
        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);
        $noGitignore = (bool) $input->getOption(self::OPT_NO_GITIGNORE);
        $noTests = (bool) $input->getOption(self::OPT_NO_TESTS);

        $template = $input->getOption(self::OPT_TEMPLATE);

        // --list-templates, or -t/--template with no value, prints the catalog.
        if ((bool) $input->getOption(self::OPT_LIST_TEMPLATES) || $template === null) {
            $this->printTemplates($output);

            return Command::SUCCESS;
        }

        $cwd = getcwd();
        if ($cwd === false) {
            $output->writeln('<error>Unable to determine current working directory.</error>');

            return Command::FAILURE;
        }

        if ($dryRun) {
            $output->writeln('<comment>Dry run mode - no files will be created</comment>');
            $output->writeln('');
        }

        if ($template !== false) {
            return $this->scaffoldFromTemplate(
                ScalarCoercion::toString($template),
                $projectName,
                $cwd,
                $output,
                $force,
                $dryRun,
            );
        }

        $namespace = $this->namespaceNormalizer->normalize($projectName);
        $mainFilename = $this->mainFilename($layout);
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
            $cwd . '/' . $mainFilename,
            $this->templateGenerator->generateMainFile($namespace),
            $mainFilename,
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
            $this->printNextSteps($output, $mainFilename, $noTests);
        }

        return Command::SUCCESS;
    }

    private function resolveLayout(InputInterface $input): ProjectLayout
    {
        if ((bool) $input->getOption(self::OPT_MINIMAL)) {
            return ProjectLayout::Root;
        }

        if ((bool) $input->getOption(self::OPT_NESTED)) {
            return ProjectLayout::Nested;
        }

        return ProjectLayout::Flat;
    }

    private function mainFilename(ProjectLayout $layout): string
    {
        return match ($layout) {
            ProjectLayout::Root => 'main.phel',
            default => $layout->getSrcDir() . '/main.phel',
        };
    }

    private function testFilename(ProjectLayout $layout): string
    {
        return match ($layout) {
            ProjectLayout::Root => 'main_test.phel',
            default => $layout->getTestDir() . '/main_test.phel',
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

    private function printTemplates(OutputInterface $output): void
    {
        $output->writeln('<info>Available templates:</info>');
        foreach ($this->templateScaffolder->availableTemplates() as $name => $description) {
            $output->writeln(sprintf('  <comment>%s</comment> - %s', $name, $description));
        }

        $output->writeln('');
        $output->writeln('Scaffold one with: <comment>phel init my-app --template=<name></comment>');
    }

    private function scaffoldFromTemplate(
        string $template,
        string $projectName,
        string $cwd,
        OutputInterface $output,
        bool $force,
        bool $dryRun,
    ): int {
        if (!$this->templateScaffolder->hasTemplate($template)) {
            $output->writeln(sprintf('<error>Unknown template "%s".</error>', $template));
            $output->writeln('');
            $this->printTemplates($output);

            return Command::FAILURE;
        }

        foreach ($this->templateScaffolder->files($template, $projectName) as $relativePath => $content) {
            $fullPath = $cwd . '/' . $relativePath;

            if (!$dryRun && !$this->ensureParentDir($fullPath, $output)) {
                return Command::FAILURE;
            }

            if (!$this->createFile($fullPath, $content, $relativePath, $output, $force, $dryRun)) {
                return Command::FAILURE;
            }
        }

        if (!$dryRun) {
            $this->printTemplateNextSteps($output, $template);
        }

        return Command::SUCCESS;
    }

    private function ensureParentDir(string $filePath, OutputInterface $output): bool
    {
        $dir = dirname($filePath);
        if (is_dir($dir)) {
            return true;
        }

        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            $output->writeln(sprintf('<error>Failed to create directory: %s</error>', $dir));

            return false;
        }

        return true;
    }

    private function printTemplateNextSteps(OutputInterface $output, string $template): void
    {
        $output->writeln('');
        $output->writeln(sprintf('<info>Scaffolded the "%s" template!</info>', $template));
        $output->writeln('');
        $output->writeln('Next steps:');
        $output->writeln('  1. Install dependencies: <comment>composer install</comment>');
        $output->writeln('  2. Run the tests:        <comment>./vendor/bin/phel test</comment>');
        $output->writeln('  3. See the README for how to run the app.');

        ShellCompletionTip::writeTo($output);
    }

    private function printNextSteps(OutputInterface $output, string $mainFilename, bool $noTests): void
    {
        $output->writeln('');
        $output->writeln('<info>Phel project initialized successfully!</info>');
        $output->writeln('');
        $output->writeln('Next steps:');

        $step = 1;
        $output->writeln(sprintf('  %d. Install dependencies: <comment>composer install</comment>', $step++));
        $output->writeln(sprintf('  %d. Run your code:  <comment>./vendor/bin/phel run %s</comment>', $step++, $mainFilename));
        if (!$noTests) {
            $output->writeln(sprintf('  %d. Run the tests: <comment>./vendor/bin/phel test</comment>', $step++));
        }

        $output->writeln(sprintf('  %d. Start the REPL: <comment>./vendor/bin/phel repl</comment>', $step++));
        $output->writeln(sprintf('  %d. Build for production: <comment>./vendor/bin/phel build</comment>', $step));

        ShellCompletionTip::writeTo($output);
    }
}
