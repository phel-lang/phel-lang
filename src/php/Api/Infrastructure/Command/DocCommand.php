<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure\Command;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel;
use Phel\Api\ApiFacade;
use Phel\Api\ApiFactory;
use Phel\Shared\Api\PhelFunction;
use Phel\Shared\CompilerConstants;
use Phel\Shared\InvocationError;
use Phel\Shared\ScalarCoercion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

use function array_filter;
use function array_flip;
use function array_map;
use function array_values;
use function implode;
use function in_array;
use function max;
use function mb_strlen;
use function sprintf;

/**
 * @method ApiFacade getFacade()
 * @method ApiFactory getFactory()
 *
 * @internal
 */
#[ServiceMap(method: 'getFacade', className: ApiFacade::class)]
#[ServiceMap(method: 'getFactory', className: ApiFactory::class)]
final class DocCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const string OPTION_NAMESPACES = 'ns';

    private const string OPTION_FORMAT = 'format';

    private const int MIN_DESCRIPTION_WIDTH = 20;

    private const string FORMAT_TEXT = 'text';

    private const string FORMAT_JSON = 'json';

    private const array AVAILABLE_FORMATS = [self::FORMAT_TEXT, self::FORMAT_JSON];

    private const string FORMER_TEXT_FORMAT = 'table';

    private const string INTEROP_LABEL = 'php';

    protected function configure(): void
    {
        $this->setName('doc')
            ->setDescription('Display the docs for any/all phel functions')
            ->setHelp(<<<'HELP'
Prints docstrings, signatures, and examples for Phel functions.

<info>Examples:</info>
  <comment>phel doc map</comment>              Show docs for a single function
  <comment>phel doc --format=json</comment>    Emit all docs as JSON
HELP)
            ->addArgument(
                'search',
                InputArgument::OPTIONAL,
                'Search input that look for a similar function name',
                '',
                fn(CompletionInput $input): array => $this->completeFunctionNames($input),
            )
            ->addOption(
                self::OPTION_NAMESPACES,
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Specify which namespaces to load.',
                [],
                fn(CompletionInput $input): array => $this->completeNamespaces($input),
            )
            ->addOption(
                self::OPTION_FORMAT,
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format: text, json.',
                self::FORMAT_TEXT,
                self::AVAILABLE_FORMATS,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = strtolower(ScalarCoercion::toString($input->getOption(self::OPTION_FORMAT)));
        if ($format === self::FORMER_TEXT_FORMAT) {
            $format = self::FORMAT_TEXT;
        }

        if (!in_array($format, self::AVAILABLE_FORMATS, true)) {
            return InvocationError::report($output, sprintf(
                'Unknown format: %s. Known: %s.',
                $format,
                implode(', ', self::AVAILABLE_FORMATS),
            ));
        }

        $namespaces = $this->normalizeNamespaces(ScalarCoercion::toStringList($input->getOption(self::OPTION_NAMESPACES)));
        $phelFunctions = $this->getFacade()->getPhelFunctions($namespaces);

        $search = ScalarCoercion::toString($input->getArgument('search'));
        $normalized = $this->normalizeGroupedFunctions($phelFunctions, $search);

        if ($format === self::FORMAT_JSON) {
            $this->printFunctionsAsJson($output, $normalized);
            return self::SUCCESS;
        }

        $exactMatches = $this->exactMatches($phelFunctions, $search);
        if ($exactMatches !== []) {
            $this->printFullDocs($output, $exactMatches);
            return self::SUCCESS;
        }

        if ($normalized === []) {
            $this->printNoMatches($output, $search);
            return self::SUCCESS;
        }

        if ($search !== '') {
            $output->writeln(sprintf('<comment>No exact match for "%s". Closest:</comment>', OutputFormatter::escape($search)));
        }

        $this->printFunctionsAsTable($output, $normalized);

        return self::SUCCESS;
    }

    /**
     * A search naming a function exactly, bare (`reduce-kv`) or with its
     * namespace label (`string/upper-case`), wants that function's doc, not a
     * similarity table that also lists `reduce`.
     *
     * @param list<PhelFunction> $phelFunctions
     *
     * @return list<PhelFunction>
     */
    private function exactMatches(array $phelFunctions, string $search): array
    {
        if ($search === '') {
            return [];
        }

        return array_values(array_filter(
            $phelFunctions,
            static fn(PhelFunction $fn): bool => $fn->name === $search || $fn->namespace . '/' . $fn->name === $search,
        ));
    }

    /**
     * @param list<PhelFunction> $phelFunctions
     */
    private function printFullDocs(OutputInterface $output, array $phelFunctions): void
    {
        $formatter = $this->getFactory()->createDocViewFormatter();
        $views = array_map($formatter->format(...), $phelFunctions);

        $output->writeln(OutputFormatter::escape(implode("\n\n", $views)));
    }

    /**
     * A search that matches nothing is not a failure: `phel doc` is a
     * similarity search over the documented functions, and "nothing is close
     * enough" is a legitimate answer, so the exit code stays `SUCCESS` and only
     * the message changes. Printing the bare header-only table told the user
     * nothing about which of the two happened.
     *
     * The `json` format keeps emitting `[]`, which is already an unambiguous
     * machine-readable "no matches".
     */
    private function printNoMatches(OutputInterface $output, string $search): void
    {
        if ($search === '') {
            $output->writeln('<comment>No documented functions found.</comment>');
            $output->writeln('Check the namespaces passed to --ns, or drop the option to search all of them.');

            return;
        }

        $output->writeln(sprintf('<comment>No function matches "%s".</comment>', OutputFormatter::escape($search)));
        $output->writeln('Try a shorter search term, or run `phel doc` with no argument to list every documented function.');
    }

    /**
     * Suggests fully qualified function names (`namespace/name`) matching the
     * partial value the user has typed so far. Powers `phel doc <TAB>`.
     *
     * @return list<string>
     */
    private function completeFunctionNames(CompletionInput $input): array
    {
        $typed = $input->getCompletionValue();

        $names = [];
        /** @var PhelFunction $phelFunction */
        foreach ($this->getFacade()->getPhelFunctions() as $phelFunction) {
            $fnName = $phelFunction->namespace . '/' . $phelFunction->name;
            if ($typed === '' || str_contains($fnName, $typed)) {
                $names[] = $fnName;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Suggests the distinct namespaces that own at least one documented
     * function. Powers `phel doc --ns=<TAB>`.
     *
     * @return list<string>
     */
    private function completeNamespaces(CompletionInput $input): array
    {
        $typed = $input->getCompletionValue();

        $namespaces = [];
        /** @var PhelFunction $phelFunction */
        foreach ($this->getFacade()->getPhelFunctions() as $phelFunction) {
            $ns = $phelFunction->namespace;
            if (($typed === '' || str_contains($ns, $typed)) && !in_array($ns, $namespaces, true)) {
                $namespaces[] = $ns;
            }
        }

        sort($namespaces);

        return $namespaces;
    }

    /**
     * @param list<string> $namespaces
     *
     * @return list<string>
     */
    private function normalizeNamespaces(array $namespaces): array
    {
        array_walk($namespaces, static function (string &$ns): void {
            if (!in_array($ns, ['core', 'http', 'html', 'test', 'json'], true)) {
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
     * @param list<array{
     *     percent:int,
     *     namespace:string,
     *     name:string,
     *     requireNs:string|null,
     *     require:string|null,
     *     signatures:list<string>,
     *     doc:string,
     *     description:string,
     *     githubUrl:string,
     *     docUrl:string,
     *     example:string
     * }> $phelFunctions
     */
    private function printFunctionsAsTable(OutputInterface $output, array $phelFunctions): void
    {
        $longestName = max([0, ...array_map(static fn(array $func): int => mb_strlen($func['name']), $phelFunctions)]);
        [$width1, $width2, $width3] = $this->calculateWithProportionalToCurrentScreen($longestName);

        $table = new Table($output)
            ->setHeaders(['function', 'signature', 'description'])
            ->setColumnMaxWidth(0, $width1)
            ->setColumnMaxWidth(1, $width2)
            ->setColumnMaxWidth(2, $width3);

        foreach ($phelFunctions as $func) {
            $table->addRow([$func['name'], implode(', ', $func['signatures']), $func['description']]);
        }

        $table->render();
    }

    /**
     * @param list<array{
     *     percent:int,
     *     namespace:string,
     *     name:string,
     *     requireNs:string|null,
     *     require:string|null,
     *     signatures:list<string>,
     *     doc:string,
     *     description:string,
     *     githubUrl:string,
     *     docUrl:string,
     *     example:string,
     * }> $phelFunctions
     */
    private function printFunctionsAsJson(OutputInterface $output, array $phelFunctions): void
    {
        $jsonData = array_map(static function (array $func): array {
            unset($func['percent']);
            return $func;
        }, $phelFunctions);

        $output->writeln(json_encode($jsonData, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    /**
     * @return array{0:int, 1:int, 2:int}
     */
    private function calculateWithProportionalToCurrentScreen(int $longestName): array
    {
        $colCount = new Terminal()->getWidth();
        $colCountFloat = (float) $colCount;
        $proportion1 = 25;
        $proportion2 = 40;
        $proportion3 = 50;
        $totalProportion = (float) ($proportion1 + $proportion2 + $proportion3);
        // A name is one token, so the function column grows to fit the
        // longest one instead of wrapping it mid-word.
        $width1 = max($longestName, (int) (((float) $proportion1 / $totalProportion) * $colCountFloat) - 5);
        $width2 = (int) (((float) $proportion2 / $totalProportion) * $colCountFloat) - 5;
        $width3 = max(self::MIN_DESCRIPTION_WIDTH, $colCount - ($width1 + $width2 + 10));

        return [$width1, $width2, $width3];
    }

    /**
     * @param list<PhelFunction> $phelFunctions
     *
     * @return list<array{
     *   percent: int,
     *   namespace: string,
     *   name: string,
     *   requireNs: string|null,
     *   require: string|null,
     *   signatures: list<string>,
     *   doc: string,
     *   description: string,
     *   githubUrl: string,
     *   docUrl: string,
     *   example: string,
     * }>
     */
    private function normalizeGroupedFunctions(array $phelFunctions, string $search): array
    {
        $normalized = [];
        $loadedNamespaces = array_flip(Phel::getNamespaces());

        foreach ($phelFunctions as $phelFunction) {
            $fnName = $phelFunction->namespace . '/' . $phelFunction->name;
            $percent = 0.0;
            similar_text($fnName, $search, $percent);
            if ($search && $percent < 45) {
                continue;
            }

            $description = preg_replace('/\r?\n/', '', $phelFunction->description) ?? '';
            $requireNs = $this->requireNamespace($phelFunction->namespace, $loadedNamespaces);

            $normalized[] = [
                'namespace' => $phelFunction->namespace,
                'name' => $fnName,
                'requireNs' => $requireNs,
                'require' => $requireNs === null || $requireNs === CompilerConstants::PHEL_CORE_NAMESPACE
                    ? null
                    : sprintf('(:require %s :refer [%s])', $requireNs, $phelFunction->name),
                'signatures' => $phelFunction->signatures,
                'doc' => $phelFunction->doc,
                'description' => $description,
                'example' => ScalarCoercion::toString($phelFunction->meta['example'] ?? null),
                'githubUrl' => $phelFunction->githubUrl,
                'docUrl' => $phelFunction->docUrl,
                'percent' => (int) round($percent),
            ];
        }

        usort($normalized, static fn(array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return $normalized;
    }

    /**
     * The `namespace` label drops the `phel.` prefix of the stdlib (`string`
     * for `phel.string`), and a `php/...` special form has nothing to require.
     *
     * @param array<string, int> $loadedNamespaces
     */
    private function requireNamespace(string $label, array $loadedNamespaces): ?string
    {
        if ($label === self::INTEROP_LABEL) {
            return null;
        }

        if ($label === 'core') {
            return CompilerConstants::PHEL_CORE_NAMESPACE;
        }

        $stdlib = 'phel.' . $label;

        return isset($loadedNamespaces[$stdlib]) ? $stdlib : $label;
    }
}
