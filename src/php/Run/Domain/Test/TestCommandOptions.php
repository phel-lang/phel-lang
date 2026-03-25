<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Test;

use Phel\Printer\Printer;

use function sprintf;

final readonly class TestCommandOptions
{
    public const string FILTER = 'filter';

    public const string TESTDOX = 'testdox';

    public const string FAIL_FAST = 'fail-fast';

    private function __construct(
        private ?string $filter,
        private bool $testdox,
        private bool $failFast,
    ) {}

    public static function empty(): self
    {
        return self::fromArray([self::FILTER => null]);
    }

    public static function fromArray(array $options): self
    {
        return new self(
            $options[self::FILTER] ?? null,
            !empty($options[self::TESTDOX]),
            !empty($options[self::FAIL_FAST]),
        );
    }

    public function asPhelHashMap(): string
    {
        $printer = Printer::readable();

        $filter = $this->filter === null
            ? 'nil'
            : $printer->print($this->filter);

        return sprintf(
            '{:filter %s :testdox %s :fail-fast %s}',
            $filter,
            $printer->print($this->testdox),
            $printer->print($this->failFast),
        );
    }
}
