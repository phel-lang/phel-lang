<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Test;

use Phel\Printer\Printer;

use function is_array;
use function is_int;
use function is_string;

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

    public const string LIST_ONLY = 'list-only';

    public const string ONLY_TESTS = 'only-tests';

    public const string LAST_FAILED_FILE = 'last-failed-file';

    public const string SLOWEST = 'slowest';

    public const string REPEAT = 'repeat';

    public const string SEED = 'seed';

    public const string RANDOM_ORDER = 'random-order';

    /**
     * @param list<string> $reporters
     * @param list<string> $filters
     * @param list<string> $includes
     * @param list<string> $excludes
     * @param list<string> $nsPatterns
     * @param list<string> $onlyTests
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
        private bool $listOnly,
        private array $onlyTests,
        private ?string $lastFailedFile,
        private int $slowest,
        private int $repeat,
        private ?int $seed,
        private bool $randomOrder,
    ) {}

    public static function empty(): self
    {
        return self::fromArray([self::FILTER => null]);
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function fromArray(array $options): self
    {
        /** @var list<string> $reporters */
        $reporters = self::normalizeStringList($options[self::REPORTERS] ?? null);

        $junitOutput = $options[self::JUNIT_OUTPUT] ?? null;
        if ($junitOutput === '') {
            $junitOutput = null;
        }

        $lastFailedFile = $options[self::LAST_FAILED_FILE] ?? null;
        if ($lastFailedFile === '') {
            $lastFailedFile = null;
        }

        $slowest = (int) ($options[self::SLOWEST] ?? 0);
        if ($slowest < 0) {
            $slowest = 0;
        }

        $repeat = (int) ($options[self::REPEAT] ?? 1);
        if ($repeat < 1) {
            $repeat = 1;
        }

        $seedRaw = $options[self::SEED] ?? null;
        $seed = is_int($seedRaw) ? $seedRaw : null;

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
            !empty($options[self::LIST_ONLY]),
            self::normalizeStringList($options[self::ONLY_TESTS] ?? null),
            is_string($lastFailedFile) ? $lastFailedFile : null,
            $slowest,
            $repeat,
            $seed,
            !empty($options[self::RANDOM_ORDER]),
        );
    }

    public function asPhelHashMap(): string
    {
        $printer = Printer::readable();
        $entries = [];

        $entries[] = ':filter ' . ($this->filter === null ? 'nil' : $printer->print($this->filter));
        $entries[] = ':testdox ' . $printer->print($this->testdox);
        $entries[] = ':fail-fast ' . $printer->print($this->failFast);
        $entries[] = ':stack-trace ' . $printer->print($this->stackTrace);

        $this->appendKeywordVector($entries, ':reporters', $this->reporters);
        $this->appendOptionalString($entries, ':junit-output', $this->junitOutput, $printer);
        $this->appendKeywordVector($entries, ':include', $this->includes);
        $this->appendKeywordVector($entries, ':exclude', $this->excludes);
        $this->appendStringVector($entries, ':ns-patterns', $this->nsPatterns, $printer);
        $this->appendStringVector($entries, ':filters', $this->filters, $printer);
        if ($this->listOnly) {
            $entries[] = ':list-only true';
        }

        $this->appendStringVector($entries, ':only-tests', $this->onlyTests, $printer);
        $this->appendOptionalString($entries, ':last-failed-file', $this->lastFailedFile, $printer);
        if ($this->slowest > 0) {
            $entries[] = ':slowest ' . $this->slowest;
        }

        if ($this->repeat > 1) {
            $entries[] = ':repeat ' . $this->repeat;
        }

        if ($this->seed !== null) {
            $entries[] = ':seed ' . $this->seed;
        }

        if ($this->randomOrder) {
            $entries[] = ':random-order true';
        }

        return '{' . implode(' ', $entries) . '}';
    }

    /**
     * @param list<string> $entries
     */
    private function appendOptionalString(array &$entries, string $key, ?string $value, Printer $printer): void
    {
        if ($value === null) {
            return;
        }

        $entries[] = $key . ' ' . $printer->print($value);
    }

    /**
     * @param list<string> $entries
     * @param list<string> $values
     */
    private function appendKeywordVector(array &$entries, string $key, array $values): void
    {
        if ($values === []) {
            return;
        }

        $entries[] = $key . ' [' . implode(' ', array_map(static fn(string $name): string => ':' . $name, $values)) . ']';
    }

    /**
     * @param list<string> $entries
     * @param list<string> $values
     */
    private function appendStringVector(array &$entries, string $key, array $values, Printer $printer): void
    {
        if ($values === []) {
            return;
        }

        $entries[] = $key . ' [' . implode(' ', array_map($printer->print(...), $values)) . ']';
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
}
