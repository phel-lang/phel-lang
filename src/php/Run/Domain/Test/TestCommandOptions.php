<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Test;

use Phel\Printer\Printer;

use function sprintf;

final readonly class TestCommandOptions
{
    public const string FILTER = 'filter';

    public const string TESTDOX = 'testdox';

    private function __construct(
        private ?string $filter,
        private bool $testdox,
    ) {
    }

    public static function empty(): self
    {
        return self::fromArray([self::FILTER => null]);
    }

    public static function fromArray(array $options): self
    {
        return new self(
            $options[self::FILTER] ?? null,
            !empty($options[self::TESTDOX]),
        );
    }

    public function asPhelHashMap(): string
    {
        $printer = Printer::readable();

        $filter = $this->filter === null
            ? 'nil'
            : $printer->print($this->filter);

        return sprintf(
            '{:filter %s :testdox %s}',
            $filter,
            $printer->print($this->testdox),
        );
    }
}
