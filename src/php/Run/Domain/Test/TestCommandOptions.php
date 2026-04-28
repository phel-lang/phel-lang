<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Test;

use Phel\Printer\Printer;

use function is_array;
use function is_string;
use function sprintf;

final readonly class TestCommandOptions
{
    public const string FILTER = 'filter';

    public const string TESTDOX = 'testdox';

    public const string FAIL_FAST = 'fail-fast';

    public const string STACK_TRACE = 'stack-trace';

    public const string REPORTERS = 'reporters';

    public const string JUNIT_OUTPUT = 'junit-output';

    public const string INCLUDE = 'include';

    public const string EXCLUDE = 'exclude';

    public const string NS_PATTERNS = 'ns-patterns';

    public const string FILTERS = 'filters';

    /**
     * @param list<string> $reporters
     * @param list<string> $filters
     * @param list<string> $includes
     * @param list<string> $excludes
     * @param list<string> $nsPatterns
     */
    private function __construct(
        private ?string $filter,
        private bool $testdox,
        private bool $failFast,
        private bool $stackTrace,
        private array $reporters,
        private ?string $junitOutput,
        private array $includes,
        private array $excludes,
        private array $nsPatterns,
        private array $filters,
    ) {}

    public static function empty(): self
    {
        return self::fromArray([self::FILTER => null]);
    }

    public static function fromArray(array $options): self
    {
        /** @var list<string> $reporters */
        $reporters = self::normalizeStringList($options[self::REPORTERS] ?? null);

        $junitOutput = $options[self::JUNIT_OUTPUT] ?? null;
        if ($junitOutput === '') {
            $junitOutput = null;
        }

        return new self(
            $options[self::FILTER] ?? null,
            !empty($options[self::TESTDOX]),
            !empty($options[self::FAIL_FAST]),
            !empty($options[self::STACK_TRACE]),
            $reporters,
            is_string($junitOutput) ? $junitOutput : null,
            self::normalizeStringList($options[self::INCLUDE] ?? null),
            self::normalizeStringList($options[self::EXCLUDE] ?? null),
            self::normalizeStringList($options[self::NS_PATTERNS] ?? null),
            self::normalizeStringList($options[self::FILTERS] ?? null),
        );
    }

    public function asPhelHashMap(): string
    {
        $printer = Printer::readable();

        return sprintf(
            '{:filter %s :testdox %s :fail-fast %s :stack-trace %s%s%s%s%s%s%s}',
            $this->filter === null ? 'nil' : $printer->print($this->filter),
            $printer->print($this->testdox),
            $printer->print($this->failFast),
            $printer->print($this->stackTrace),
            $this->keywordVector(':reporters', $this->reporters),
            $this->optionalString(':junit-output', $this->junitOutput, $printer),
            $this->keywordVector(':include', $this->includes),
            $this->keywordVector(':exclude', $this->excludes),
            $this->stringVector(':ns-patterns', $this->nsPatterns),
            $this->stringVector(':filters', $this->filters),
        );
    }

    private function optionalString(string $key, ?string $value, Printer $printer): string
    {
        return $value === null ? '' : ' ' . $key . ' ' . $printer->print($value);
    }

    /**
     * @return list<string>
     */
    private static function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param list<string> $values
     */
    private function keywordVector(string $key, array $values): string
    {
        return $this->renderVector(
            $key,
            $values,
            static fn(string $name): string => ':' . $name,
        );
    }

    /**
     * @param list<string> $values
     */
    private function stringVector(string $key, array $values): string
    {
        return $this->renderVector($key, $values, Printer::readable()->print(...));
    }

    /**
     * @param list<string>             $values
     * @param callable(string): string $mapper
     */
    private function renderVector(string $key, array $values, callable $mapper): string
    {
        if ($values === []) {
            return '';
        }

        return ' ' . $key . ' [' . implode(' ', array_map($mapper, $values)) . ']';
    }
}
