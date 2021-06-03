<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\QuoteSymbol;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class QuoteSymbolTest extends TestCase
{
    public function test_list_with_wrong_symbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("This is not a 'quote.");

        $list = TypeFactory::getInstance()->persistentListFromArray(['any symbol', 'any text']);
        (new QuoteSymbol())->analyze($list, NodeEnvironment::empty());
    }

    public function test_list_without_argument(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Exactly one argument is required for 'quote");

        $list = TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_QUOTE)]);
        (new QuoteSymbol())->analyze($list, NodeEnvironment::empty());
    }

    public function test_quote_list_with_any_text(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_QUOTE), 'any text']);
        $symbol = (new QuoteSymbol())->analyze($list, NodeEnvironment::empty());

        self::assertSame('any text', $symbol->getValue());
    }
}
