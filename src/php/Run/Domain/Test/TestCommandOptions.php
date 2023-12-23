<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Test;

use Phel\Lang\TypeFactory;
use Phel\Printer\Printer;

final readonly class TestCommandOptions
{
    public const FILTER = 'filter';

    public const TESTDOX = 'testdox';

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
        $typeFactory = TypeFactory::getInstance();

        $optionsMap = $typeFactory->persistentMapFromKVs(
            $typeFactory->keyword(self::FILTER),
            $this->filter,
            $typeFactory->keyword(self::TESTDOX),
            $this->testdox,
        );

        return Printer::readable()->print($optionsMap);
    }
}
