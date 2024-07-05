<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\MacroExpandSymbol;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class MacroExpandSymbolTest extends TestCase
{
    public function test_list_with_wrong_symbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("This is not a 'macroexpand.");

        $list = TypeFactory::getInstance()->persistentListFromArray(['any symbol', 'any text']);
        (new MacroExpandSymbol())->analyze($list, NodeEnvironment::empty());
    }

    public function test_list_without_argument(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Exactly one argument is required for 'quote");

        $list = TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_MACRO_EXPAND)]);
        (new MacroExpandSymbol())->analyze($list, NodeEnvironment::empty());
    }

    public function test_macro_expand_list_with_any_text(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_MACRO_EXPAND), 'any text']);
        $symbol = (new MacroExpandSymbol())->analyze($list, NodeEnvironment::empty());

        self::assertSame('any text', $symbol->getValue());
    }
}
