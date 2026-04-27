<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\UseNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\UseSymbol;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class UseSymbolTest extends TestCase
{
    private Analyzer $analyzer;

    protected function setUp(): void
    {
        Registry::getInstance()->clear();
        $globalEnv = new GlobalEnvironment();
        $this->analyzer = new Analyzer($globalEnv);
        $this->analyzer->setNamespace('test\\ns');
    }

    public function test_requires_at_least_one_argument(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("'use requires at least one argument");

        $list = Phel::list([Symbol::create(Symbol::NAME_USE)]);
        $this->analyze($list);
    }

    public function test_registers_fully_qualified_alias_in_current_namespace(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_USE),
            Symbol::create(Keyword::class),
        ]);

        $node = $this->analyze($list);

        self::assertInstanceOf(UseNode::class, $node);

        $aliases = $this->getGlobalEnvironment()->getUseAliases('test\\ns');
        self::assertArrayHasKey('Keyword', $aliases);
        self::assertSame('\\' . Keyword::class, $aliases['Keyword']->getName());
    }

    public function test_registers_multiple_aliases(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_USE),
            Symbol::create(Keyword::class),
            Symbol::create(Symbol::class),
        ]);

        $this->analyze($list);

        $aliases = $this->getGlobalEnvironment()->getUseAliases('test\\ns');
        self::assertArrayHasKey('Keyword', $aliases);
        self::assertArrayHasKey('Symbol', $aliases);
    }

    public function test_supports_as_alias(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_USE),
            Symbol::create(Keyword::class),
            Keyword::create('as'),
            Symbol::create('K'),
        ]);

        $this->analyze($list);

        $aliases = $this->getGlobalEnvironment()->getUseAliases('test\\ns');
        self::assertArrayHasKey('K', $aliases);
        self::assertSame('\\' . Keyword::class, $aliases['K']->getName());
    }

    public function test_rejects_non_symbol_import(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('First argument in use must be a symbol.');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_USE),
            123,
        ]);

        $this->analyze($list);
    }

    public function test_rejects_unknown_import(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Cannot import unknown PHP symbol \\Missing\\UseClass.');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_USE),
            Symbol::create('Missing\\UseClass'),
        ]);

        $this->analyze($list);
    }

    public function test_preserves_source_location(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_USE),
            Symbol::create(Keyword::class),
        ]);

        $node = $this->analyze($list);

        self::assertSame($list->getStartLocation(), $node->getStartSourceLocation());
    }

    private function analyze(PersistentListInterface $list): UseNode
    {
        return new UseSymbol($this->analyzer)->analyze($list, NodeEnvironment::empty());
    }

    private function getGlobalEnvironment(): GlobalEnvironment
    {
        $ref = new ReflectionProperty(Analyzer::class, 'globalEnvironment');

        return $ref->getValue($this->analyzer);
    }
}
