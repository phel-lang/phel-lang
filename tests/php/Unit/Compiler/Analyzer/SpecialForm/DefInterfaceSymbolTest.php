<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefInterfaceNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefInterfaceSymbol;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\AbstractLocatedException;
use PHPUnit\Framework\TestCase;

final class DefInterfaceSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_typed_constants_are_parsed(): void
    {
        $node = $this->analyze(Phel::list([
            Symbol::create(Symbol::NAME_DEF_INTERFACE),
            Symbol::create('HasMax'),
            Keyword::create('const', 'php'),
            Phel::list([Symbol::create('MAX'), 100]),
            Phel::list([Symbol::create('LABEL'), 'max']),
        ]));

        self::assertCount(2, $node->getConsts());
        self::assertSame('MAX', $node->getConsts()[0]->getName()->getName());
        self::assertSame(100, $node->getConsts()[0]->getValue());
        self::assertSame('max', $node->getConsts()[1]->getValue());
    }

    public function test_constant_name_must_be_a_symbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('A :php/const name must be a symbol');

        $this->analyze(Phel::list([
            Symbol::create(Symbol::NAME_DEF_INTERFACE),
            Symbol::create('Bad'),
            Keyword::create('const', 'php'),
            Phel::list(['not-a-symbol', 1]),
        ]));
    }

    public function test_constant_must_be_name_value_pair(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('A :php/const must be (NAME value)');

        $this->analyze(Phel::list([
            Symbol::create(Symbol::NAME_DEF_INTERFACE),
            Symbol::create('Bad'),
            Keyword::create('const', 'php'),
            Phel::list([Symbol::create('MAX'), 1, 2]),
        ]));
    }

    public function test_constant_value_must_be_scalar(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('A :php/const value must be an int, float, string, bool or nil');

        $this->analyze(Phel::list([
            Symbol::create(Symbol::NAME_DEF_INTERFACE),
            Symbol::create('Bad'),
            Keyword::create('const', 'php'),
            Phel::list([Symbol::create('MAX'), Keyword::create('not-scalar')]),
        ]));
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function analyze(PersistentListInterface $list): DefInterfaceNode
    {
        return new DefInterfaceSymbol($this->analyzer)
            ->analyze($list, NodeEnvironment::empty());
    }
}
