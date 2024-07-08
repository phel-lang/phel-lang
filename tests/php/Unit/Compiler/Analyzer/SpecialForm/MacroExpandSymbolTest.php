<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MacroExpandNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\MacroExpandSymbol;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class MacroExpandSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(
            new GlobalEnvironment()
        );
    }

    public function test_list_with_wrong_symbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("This is not a 'macroexpand.");

        $list = TypeFactory::getInstance()
            ->persistentListFromArray(['any symbol', 'any text']);

        $this->analyze($list);
    }

    public function test_list_without_argument(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Exactly one argument is required for 'macroexpand");

        $list = TypeFactory::getInstance()
            ->persistentListFromArray([
                Symbol::create(Symbol::NAME_MACRO_EXPAND)
            ]);

        $this->analyze($list);
    }

    public function test_macro_expand_list_with_any_text(): void
    {
        $list = TypeFactory::getInstance()
            ->persistentListFromArray([
                Symbol::create(Symbol::NAME_MACRO_EXPAND),
                'any text'
            ]);

        $symbol = $this->analyze($list);
        $value = $symbol->getValue();
        self::assertSame('any text', $value->getValue());
    }

    private function analyze(PersistentListInterface $list): MacroExpandNode
    {
        return (new MacroExpandSymbol($this->analyzer))
            ->analyze($list, NodeEnvironment::empty());
    }
}
