<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Api\ApiFacade;
use Phel\Api\Transfer\PhelFunction;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function in_array;
use function strlen;

/**
 * @method ApiFacade getFacade()
 */
final class DocCommand extends Command
{
    use DocBlockResolverAwareTrait;

    private const OPTION_NAMESPACES = 'ns';

    protected function configure(): void
    {
        $this->setName('doc')
            ->setDescription('Display the docs for any/all phel functions')
            ->addArgument('search', InputArgument::OPTIONAL, 'Search input that look for a similar function name', '')
            ->addOption('table', 't', InputOption::VALUE_NONE, 'Display a table')
            ->addOption(
                self::OPTION_NAMESPACES,
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Specify which namespaces to load.',
                [],
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $namespaces = $this->normalizeNamespaces($input->getOption(self::OPTION_NAMESPACES));
        $phelFunctions = $this->getFacade()->getPhelFunctions($namespaces);

        $search = $input->getArgument('search');
        if ($input->getOption('table')) {
            $this->printFunctionsAsTable($output, $phelFunctions, $search);
        } else {
            $this->printFunctionsAsText($output, $phelFunctions, $search);
        }

        return self::SUCCESS;
    }

    private function normalizeNamespaces(array $namespaces): array
    {
        array_walk($namespaces, static function (string &$ns): void {
            if (!in_array($ns, ['core', 'http', 'html', 'test', 'json'])) {
                return;
            }

            if (str_starts_with($ns, 'phel\\')) {
                return;
            }

            $ns = 'phel\\' . $ns;
        });

        return $namespaces;
    }

    /**
     * @param list<PhelFunction> $phelFunctions
     */
    private function printFunctionsAsText(OutputInterface $output, array $phelFunctions, string $search): void
    {
        [$normalized, $longestFuncNameLength] = $this->normalizeGroupedFunctions($phelFunctions, $search);

        foreach ($normalized as $func) {
            $output->writeln(
                sprintf(
                    '  %s: %s - %s',
                    str_pad($func['name'], $longestFuncNameLength),
                    $func['signature'],
                    $func['description'],
                ),
            );
        }
    }

    /**
     * @param list<PhelFunction> $phelFunctions
     */
    private function printFunctionsAsTable(OutputInterface $output, array $phelFunctions, string $search): void
    {
        [$width1, $width2, $width3] = $this->calculateWithProportionalToCurrentScreen();

        $table = (new Table($output))
            ->setHeaders(['function', 'signature', 'description'])
            ->setColumnMaxWidth(0, $width1)
            ->setColumnMaxWidth(1, $width2)
            ->setColumnMaxWidth(2, $width3);

        [$normalized] = $this->normalizeGroupedFunctions($phelFunctions, $search);

        foreach ($normalized as $func) {
            $table->addRow([$func['name'], $func['signature'], $func['description']]);
        }

        $table->render();
    }

    /**
     * @return array{0:int, 1:int, 2:int}
     */
    private function calculateWithProportionalToCurrentScreen(): array
    {
        /** @psalm-suppress ForbiddenCode */
        $colCount = (int)shell_exec('tput cols');
        $proportion1 = 25;
        $proportion2 = 40;
        $proportion3 = 50;
        $totalProportion = $proportion1 + $proportion2 + $proportion3;
        $width1 = (int)(($proportion1 / $totalProportion) * $colCount) - 5;
        $width2 = (int)(($proportion2 / $totalProportion) * $colCount) - 5;
        $width3 = $colCount - ($width1 + $width2 + 10);

        return [$width1, $width2, $width3];
    }

    /**
     * @param list<PhelFunction> $phelFunctions
     *
     * @return array{
     *   0: list<array{
     *     percent: int,
     *     name: string,
     *     signature: string,
     *     doc: string,
     *     description: string,
     *   }>,
     *   1: int
     * }
     */
    private function normalizeGroupedFunctions(array $phelFunctions, string $search): array
    {
        $longestFuncNameLength = 5;
        $normalized = [];

        foreach ($phelFunctions as $phelFunction) {
            $fnName = $phelFunction->fnName();
            similar_text($fnName, $search, $percent);
            if ($search && $percent < 40) {
                continue;
            }

            if (strlen($fnName) > $longestFuncNameLength) {
                $longestFuncNameLength = strlen($fnName);
            }

            $normalized[] = [
                'percent' => round($percent),
                'name' => $fnName,
                'signature' => $phelFunction->fnSignature(),
                'doc' => $phelFunction->doc(),
                'description' => preg_replace('/\r?\n/', '', $phelFunction->description()),
            ];
        }

        usort($normalized, static fn ($a, $b): int => $b['percent'] <=> $a['percent']);

        return [$normalized, $longestFuncNameLength];
    }
}
