<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure\Command;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Api\ApiFacade;
use Phel\Api\Transfer\PhelFunction;
use Symfony\Component\Console\Command\Command;
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
            ->setDescription('Display the docs for any/all phel functions.')
            ->addArgument('search', InputArgument::OPTIONAL, 'Search input that look for a similar function name', '')
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
        $this->printFunctions($output, $phelFunctions, $search);

        return self::SUCCESS;
    }

    private function normalizeNamespaces(array $namespaces): array
    {
        array_walk($namespaces, static function (string &$ns): void {
            if (in_array($ns, ['core', 'http', 'html', 'test', 'json']) && !str_starts_with($ns, 'phel\\')) {
                $ns = 'phel\\' . $ns;
            }
        });

        return $namespaces;
    }

    /**
     * @param list<PhelFunction> $phelFunctions
     */
    private function printFunctions(OutputInterface $output, array $phelFunctions, string $search): void
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

        foreach ($phelFunctions as $function) {
            $fnName = $function->fnName();
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
                'signature' => $function->fnSignature(),
                'doc' => $function->doc(),
                'description' => preg_replace('/\r?\n/', '', $function->description()),
            ];
        }
        usort($normalized, static fn ($a, $b) => $b['percent'] <=> $a['percent']);

        return [$normalized, $longestFuncNameLength];
    }
}
