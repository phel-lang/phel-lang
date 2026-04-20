<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Api\ApiFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function sprintf;

#[ServiceMap(method: 'getFacade', className: ApiFacade::class)]
final class ApiDaemonCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('api-daemon')
            ->setDescription(
                'Long-running JSON-RPC daemon exposing the Api semantic analysis facade over newline-delimited JSON (stdio).',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $daemon = $this->getFacade()->createApiDaemon();
            $daemon->run();

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));
            return self::FAILURE;
        }
    }
}
