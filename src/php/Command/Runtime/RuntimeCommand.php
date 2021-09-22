<?php

declare(strict_types=1);

namespace Phel\Command\Runtime;

use Phel\Runtime\RuntimeFacadeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RuntimeCommand extends Command
{
    private const COMMAND_NAME = 'runtime';

    private RuntimeFileGenerator $runtimeFileGenerator;
    private RuntimeFacadeInterface $runtimeFacade;

    public function __construct(
        RuntimeFileGenerator $runtimeFileGenerator,
        RuntimeFacadeInterface $runtimeFacade
    ) {
        parent::__construct(self::COMMAND_NAME);

        $this->runtimeFileGenerator = $runtimeFileGenerator;
        $this->runtimeFacade = $runtimeFacade;
    }

    protected function configure(): void
    {
        $this->setDescription('Generates the PhelRuntime file in vendor');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->runtimeFacade->loadConfig();

        file_put_contents(
            $this->runtimeFacade->getVendorDir() . '/PhelRuntime.php',
            $this->runtimeFileGenerator->generate($config)
        );

        $output->writeln('<info>PhelRuntime created/updated successfully!</info>');

        return self::SUCCESS;
    }
}
