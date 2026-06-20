<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Api\ApiFacade;
use Phel\Shared\Console\DeprecatedOptionWarner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function file_put_contents;
use function is_string;
use function json_encode;

use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

#[ServiceMap(method: 'getFacade', className: ApiFacade::class)]
final class IndexCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('index')
            ->setDescription('Build a project-level symbol index across one or more source directories')
            ->setHelp(<<<'HELP'
Scans directories for `.phel` files and indexes their symbols (for tooling).

<info>Example:</info>
  <comment>phel index src tests --output=index.json</comment>
HELP)
            ->addArgument(
                'dirs',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Directories to scan for .phel files',
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Optional path to persist the full index as JSON',
            )
            ->addOption(
                'out',
                null,
                InputOption::VALUE_REQUIRED,
                '[deprecated] use --output instead',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $dirs */
        $dirs = (array) $input->getArgument('dirs');

        $index = $this->getFacade()->indexProject($dirs);

        $summary = [
            'namespaces' => $index->countNamespaces(),
            'definitions' => $index->countDefinitions(),
            'dirs' => $dirs,
        ];

        $output->writeln(json_encode($summary, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        $deprecatedOut = $input->getOption('out');
        if (is_string($deprecatedOut) && $deprecatedOut !== '') {
            DeprecatedOptionWarner::warn($output, 'out', 'output');
        }

        $out = $input->getOption('output');
        if (!is_string($out) || $out === '') {
            $out = $deprecatedOut;
        }

        if (is_string($out) && $out !== '') {
            $written = @file_put_contents(
                $out,
                json_encode($index->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            );
            if ($written === false) {
                $output->writeln(sprintf('<error>Unable to write index to: %s</error>', $out));
                return self::FAILURE;
            }

            $output->writeln(sprintf('Index persisted to: %s', $out));
        }

        return self::SUCCESS;
    }
}
