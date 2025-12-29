<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

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
        $useFlat = (bool) $input->getOption(self::OPT_FLAT);

        $cwd = getcwd();
        if ($cwd === false) {
            $output->writeln('<error>Unable to determine current working directory.</error>');

            return Command::FAILURE;
        }

        $srcDir = $useFlat ? 'src' : 'src/phel';
        $testDir = $useFlat ? 'tests' : 'tests/phel';

        // Create directory structure
        $directories = [
            $cwd . '/' . $srcDir,
            $cwd . '/' . $testDir,
            $cwd . '/out',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                $output->writeln(sprintf('<error>Failed to create directory: %s</error>', $dir));

                return Command::FAILURE;
            }
        }

        // Create phel-config.php
        $configPath = $cwd . '/phel-config.php';
        if (!file_exists($configPath)) {
            $configContent = $this->generateConfig($projectName, $useFlat);
            if (file_put_contents($configPath, $configContent) === false) {
                $output->writeln('<error>Failed to create phel-config.php</error>');

                return Command::FAILURE;
            }

            $output->writeln('<info>Created phel-config.php</info>');
        } else {
            $output->writeln('<comment>phel-config.php already exists, skipping</comment>');
        }

        // Create core.phel
        $coreFile = $cwd . '/' . $srcDir . '/core.phel';
        if (!file_exists($coreFile)) {
            $coreContent = $this->generateCoreFile($projectName);
            if (file_put_contents($coreFile, $coreContent) === false) {
                $output->writeln('<error>Failed to create core.phel</error>');

                return Command::FAILURE;
            }

            $output->writeln(sprintf('<info>Created %s/core.phel</info>', $srcDir));
        } else {
            $output->writeln(sprintf('<comment>%s/core.phel already exists, skipping</comment>', $srcDir));
        }

        // Create .gitignore if it doesn't exist
        $gitignorePath = $cwd . '/.gitignore';
        if (!file_exists($gitignorePath)) {
            $gitignoreContent = $this->generateGitignore();
            if (file_put_contents($gitignorePath, $gitignoreContent) === false) {
                $output->writeln('<error>Failed to create .gitignore</error>');

                return Command::FAILURE;
            }

            $output->writeln('<info>Created .gitignore</info>');
        }

        $output->writeln('');
        $output->writeln('<info>Phel project initialized successfully!</info>');
        $output->writeln('');
        $output->writeln('Next steps:');
        $output->writeln(sprintf('  1. Run your code:  <comment>./vendor/bin/phel run %s/core.phel</comment>', $srcDir));
        $output->writeln('  2. Start the REPL: <comment>./vendor/bin/phel repl</comment>');
        $output->writeln('  3. Build for production: <comment>./vendor/bin/phel build</comment>');

        return Command::SUCCESS;
    }

    private function generateConfig(string $projectName, bool $useFlat): string
    {
        $namespace = str_replace('-', '', $projectName) . '\\core';

        if ($useFlat) {
            return <<<PHP
<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return PhelConfig::forProject('{$namespace}')
    ->useFlatLayout();

PHP;
        }

        return <<<PHP
<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;

return PhelConfig::forProject('{$namespace}');

PHP;
    }

    private function generateCoreFile(string $projectName): string
    {
        $namespace = str_replace('-', '', $projectName) . '\\core';

        return <<<PHEL
(ns {$namespace})

(defn main []
  (println "Hello from Phel!"))

(main)

PHEL;
    }

    private function generateGitignore(): string
    {
        return <<<GITIGNORE
/vendor/
/out/
/src/PhelGenerated/
*.phar
.phpunit.result.cache
phel-config-local.php

GITIGNORE;
    }
}
