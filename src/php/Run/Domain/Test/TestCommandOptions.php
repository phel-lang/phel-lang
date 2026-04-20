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

    public const string REPORTERS = 'reporters';

    public const string JUNIT_OUTPUT = 'junit-output';

    /**
     * @param list<string> $reporters
     */
    private function __construct(
        private ?string $filter,
        private bool $testdox,
        private bool $failFast,
        private array $reporters,
        private ?string $junitOutput,
    ) {}

    public static function empty(): self
    {
        return self::fromArray([self::FILTER => null]);
    }

    public static function fromArray(array $options): self
    {
        /** @var list<string> $reporters */
        $reporters = [];
        if (isset($options[self::REPORTERS]) && is_array($options[self::REPORTERS])) {
            foreach ($options[self::REPORTERS] as $reporter) {
                if (is_string($reporter) && $reporter !== '') {
                    $reporters[] = $reporter;
                }
            }
        }

        $junitOutput = $options[self::JUNIT_OUTPUT] ?? null;
        if ($junitOutput === '') {
            $junitOutput = null;
        }

        return new self(
            $options[self::FILTER] ?? null,
            !empty($options[self::TESTDOX]),
            !empty($options[self::FAIL_FAST]),
            $reporters,
            is_string($junitOutput) ? $junitOutput : null,
        );
    }

    public function asPhelHashMap(): string
    {
        $printer = Printer::readable();

        $filter = $this->filter === null
            ? 'nil'
            : $printer->print($this->filter);

        $reportersPart = '';
        if ($this->reporters !== []) {
            $reporterKeywords = array_map(
                static fn(string $name): string => ':' . $name,
                $this->reporters,
            );
            $reportersPart = ' :reporters [' . implode(' ', $reporterKeywords) . ']';
        }

        $junitPart = $this->junitOutput === null
            ? ''
            : ' :junit-output ' . $printer->print($this->junitOutput);

        return sprintf(
            '{:filter %s :testdox %s :fail-fast %s%s%s}',
            $filter,
            $printer->print($this->testdox),
            $printer->print($this->failFast),
            $reportersPart,
            $junitPart,
        );
    }
}
