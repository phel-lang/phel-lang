<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Test;

use Phel\Printer\Printer;
use PhelType;

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
        $optionsMap = PhelType::persistentMapFromKVs(
            PhelType::keyword(self::FILTER),
            $this->filter,
            PhelType::keyword(self::TESTDOX),
            $this->testdox,
        );

        return Printer::readable()->print($optionsMap);
    }
}
