<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Run\RunFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method RunFacade getFacade()
 */
class NsCommand extends Command
{
    use DocBlockResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('ns')
            ->setAliases(['loaded-ns'])
            ->setDescription('Display all loaded namespaces');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->getFacade()->getLoadedNamespaces() as $ns) {
            $output->writeln($ns);
        }

        return self::SUCCESS;
    }
}
