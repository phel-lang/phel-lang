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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectName = (string) $input->getArgument(self::ARG_PROJECT_NAME);
        $layout = $input->getOption(self::OPT_FLAT)
            ? ProjectLayout::Flat
            : ProjectLayout::Conventional;

        $cwd = getcwd();
        if ($cwd === false) {
            $output->writeln('<error>Unable to determine current working directory.</error>');

            return Command::FAILURE;
        }

        $srcDir = $layout->getSrcDir();
        $testDir = $layout->getTestDir();
        $namespace = $this->namespaceNormalizer->normalize($projectName);

        if (!$this->createDirectories($cwd, [$srcDir, $testDir, self::DIR_OUT], $output)) {
            return Command::FAILURE;
        }

        if (!$this->createFileIfNotExists(
            $cwd . '/phel-config.php',
            $this->templateGenerator->generateConfig($namespace, $layout),
            'phel-config.php',
            $output,
        )) {
            return Command::FAILURE;
        }

        if (!$this->createFileIfNotExists(
            $cwd . '/' . $srcDir . '/core.phel',
            $this->templateGenerator->generateCoreFile($namespace),
            $srcDir . '/core.phel',
            $output,
        )) {
            return Command::FAILURE;
        }

        $this->createFileIfNotExists(
            $cwd . '/.gitignore',
            $this->templateGenerator->generateGitignore(),
            '.gitignore',
            $output,
            skipExistsMessage: true,
        );

        $this->printNextSteps($output, $srcDir);

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $directories
     */
    private function createDirectories(string $basePath, array $directories, OutputInterface $output): bool
    {
        foreach ($directories as $dir) {
            $fullPath = $basePath . '/' . $dir;
            if (!is_dir($fullPath) && !mkdir($fullPath, 0755, true)) {
                $output->writeln(sprintf('<error>Failed to create directory: %s</error>', $fullPath));

                return false;
            }
        }

        return true;
    }

    private function createFileIfNotExists(
        string $path,
        string $content,
        string $displayName,
        OutputInterface $output,
        bool $skipExistsMessage = false,
    ): bool {
        if (file_exists($path)) {
            if (!$skipExistsMessage) {
                $output->writeln(sprintf('<comment>%s already exists, skipping</comment>', $displayName));
            }

            return true;
        }

        if (file_put_contents($path, $content) === false) {
            $output->writeln(sprintf('<error>Failed to create %s</error>', $displayName));

            return false;
        }

        $output->writeln(sprintf('<info>Created %s</info>', $displayName));

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
