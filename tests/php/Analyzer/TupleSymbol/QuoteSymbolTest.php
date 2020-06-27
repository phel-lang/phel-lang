<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Exceptions\PhelCodeException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;
use PHPUnit\Framework\TestCase;

final class QuoteSymbolTest extends TestCase
{
    /** @test */
    public function dataTupleDifferentThanTwo(): void
    {
        $this->expectException(PhelCodeException::class);
        $this->expectExceptionMessage("Exactly one arguments is required for 'quote");

        $tuple = new Tuple(
            [Symbol::NAME_QUOTE]
        );

        (new QuoteSymbol())(
            $tuple,
            NodeEnvironment::empty()
        );
    }

    /** @test */
    public function quoteSampleText(): void
    {
        $tuple = new Tuple(
            [Symbol::NAME_QUOTE, 'dummy text']
        );

        $symbol = (new QuoteSymbol())(
            $tuple,
            NodeEnvironment::empty()
        );

        self::assertSame('dummy text', $symbol->getValue());
    }
}
