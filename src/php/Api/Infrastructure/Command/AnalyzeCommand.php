<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel\Api\ApiFacade;
use Phel\Api\Transfer\Diagnostic;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function file_get_contents;
use function is_file;
use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

#[ServiceMap(method: 'getFacade', className: ApiFacade::class)]
final class AnalyzeCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('analyze')
            ->setDescription('Run semantic analysis on a single Phel source file and emit JSON diagnostics')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to a .phel source file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string) $input->getArgument('file');

        if (!is_file($file)) {
            $output->writeln(sprintf('<error>File not found: %s</error>', $file));
            return self::FAILURE;
        }

        $source = file_get_contents($file);
        if ($source === false) {
            $output->writeln(sprintf('<error>Unable to read file: %s</error>', $file));
            return self::FAILURE;
        }

        $diagnostics = $this->getFacade()->analyzeSource($source, $file);
        $payload = array_map(static fn(Diagnostic $d): array => $d->toArray(), $diagnostics);

        $output->writeln(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
