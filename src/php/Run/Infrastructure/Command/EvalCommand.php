<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Run\RunFacade;
use Phel\Run\RunFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method RunFacade getFacade()
 * @method RunFactory getFactory()
 */
final class EvalCommand extends Command
{
    use DocBlockResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('eval')
            ->setDescription('Evaluate a Phel expression and print the result')
            ->addArgument(
                'expression',
                InputArgument::REQUIRED,
                'The Phel expression to evaluate',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->getFacade()->loadPhelNamespaces();

        $expression = $input->getArgument('expression');

        $result = $this->getFactory()
            ->createEvalExecutor()
            ->execute((string)$expression);

        return $result ? self::SUCCESS : self::FAILURE;
    }
}
