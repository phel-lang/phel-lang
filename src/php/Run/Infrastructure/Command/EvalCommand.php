<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Run\Domain\StdinReaderInterface;
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
#[ServiceMap(method: 'getFacade', className: RunFacade::class)]
#[ServiceMap(method: 'getFactory', className: RunFactory::class)]
final class EvalCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const string STDIN_MARKER = '-';

    public function __construct(
        private ?StdinReaderInterface $stdinReader = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('eval')
            ->setDescription('Evaluate a Phel expression and print the result')
            ->addArgument(
                'expression',
                InputArgument::OPTIONAL,
                'The Phel expression to evaluate. Use "-" to read from stdin.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->getFacade()->loadPhelNamespaces();

        $expression = (string) ($input->getArgument('expression') ?? '');
        if ($expression === self::STDIN_MARKER) {
            $expression = ($this->stdinReader ?? $this->getFactory()->createStdinReader())->read();
        }

        $result = $this->getFactory()
            ->createEvalExecutor()
            ->execute($expression);

        return $result ? self::SUCCESS : self::FAILURE;
    }
}
