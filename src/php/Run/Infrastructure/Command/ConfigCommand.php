<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Phel\Run\Domain\Config\EffectiveConfigReader;
use Phel\Run\Domain\Config\EffectiveConfigResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class ConfigCommand extends Command
{
    private const string OPT_JSON = 'json';

    public function __construct(
        private readonly EffectiveConfigReader $reader = new EffectiveConfigReader(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('config')
            ->setDescription('Show the effective Phel configuration and where it comes from')
            ->setHelp(<<<'HELP'
Prints the merged config (defaults + phel-config.php + overrides) and its source.

<info>Examples:</info>
  <comment>phel config</comment>          Human-readable, annotated with origins
  <comment>phel config --json</comment>   Machine-readable effective config
HELP)
            ->addOption(
                self::OPT_JSON,
                null,
                InputOption::VALUE_NONE,
                'Print only the effective config as JSON (machine-readable)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $effective = $this->reader->read();

        if ($input->getOption(self::OPT_JSON) === true) {
            $output->writeln($this->toJson($effective->values));

            return Command::SUCCESS;
        }

        $this->writeSources($output, $effective);
        $output->writeln('');
        $output->writeln('<comment>Effective config:</comment>');
        $output->writeln($this->toJson($effective->values));

        return Command::SUCCESS;
    }

    private function writeSources(OutputInterface $output, EffectiveConfigResult $effective): void
    {
        $output->writeln('<comment>Sources:</comment>');
        $output->writeln(sprintf(' - project root: %s', $effective->projectRoot));

        if ($effective->configFileExists) {
            $output->writeln(sprintf(' - phel-config.php: <info>found</info> (%s)', $effective->configFilePath));
        } else {
            $output->writeln(' - phel-config.php: <comment>not found</comment> — using auto-detected defaults');
        }

        if ($effective->localConfigFileExists) {
            $output->writeln(sprintf(
                ' - phel-config-local.php: <info>applied</info> (%s)',
                $effective->localConfigFilePath,
            ));
        } else {
            $output->writeln(' - phel-config-local.php: not present');
        }

        $output->writeln(sprintf(
            ' - PHEL_DIR env: %s',
            $effective->phelDirEnv ?? '(unset)',
        ));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function toJson(array $values): string
    {
        return json_encode(
            $values,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }
}
