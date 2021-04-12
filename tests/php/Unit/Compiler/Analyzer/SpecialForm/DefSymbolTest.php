<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\DefNode;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\DefSymbol;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;
use stdClass;

final class DefSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function testWithDefNotAllowed(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("'def inside of a 'def is forbidden");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_DEF),
                Symbol::create('name2'),
                1,
            ]),
        ]);
        (new DefSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function testWithWrongNumberOfArguments(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Two or three arguments are required for 'def. Got 2");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('1'),
        ]);
        (new DefSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function testFirstArgumentMustBeSymbol(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("First argument of 'def must be a Symbol.");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            'not a symbol',
            '2',
        ]);
        (new DefSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function testFalseInitValue(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('$init must be TypeInterface|string|float|int|bool|null');

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            new stdClass(),
        ]);
        (new DefSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function testInitValues(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            'any value',
        ]);
        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, $env);

        self::assertEquals(
            new DefNode(
                $env,
                'user',
                Symbol::create('name'),
                TypeFactory::getInstance()->emptyPersistentHashMap(),
                new LiteralNode(
                    $env
                        ->withContext(NodeEnvironment::CONTEXT_EXPRESSION)
                        ->withDisallowRecurFrame()
                        ->withBoundTo('user\name')
                        ->withDefAllowed(false),
                    'any value'
                )
            ),
            $defNode
        );
    }

    public function testDocstring(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            'my docstring',
            'any value',
        ]);
        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, $env);

        self::assertEquals(
            new DefNode(
                $env,
                'user',
                Symbol::create('name'),
                TypeFactory::getInstance()->persistentHashMapFromKVs(
                    new Keyword('doc'),
                    'my docstring'
                ),
                new LiteralNode(
                    $env
                        ->withContext(NodeEnvironment::CONTEXT_EXPRESSION)
                        ->withDisallowRecurFrame()
                        ->withBoundTo('user\name')
                        ->withDefAllowed(false),
                    'any value'
                )
            ),
            $defNode
        );
    }

    public function testMetaKeyword(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            new Keyword('private'),
            'any value',
        ]);
        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, $env);

        self::assertEquals(
            new DefNode(
                $env,
                'user',
                Symbol::create('name'),
                TypeFactory::getInstance()->persistentHashMapFromKVs(
                    new Keyword('private'),
                    true
                ),
                new LiteralNode(
                    $env
                        ->withContext(NodeEnvironment::CONTEXT_EXPRESSION)
                        ->withDisallowRecurFrame()
                        ->withBoundTo('user\name')
                        ->withDefAllowed(false),
                    'any value'
                )
            ),
            $defNode
        );
    }

    public function testMetaTableKeyword(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            TypeFactory::getInstance()->persistentHashMapFromKVs(new Keyword('private'), true),
            'any value',
        ]);
        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, $env);

        self::assertEquals(
            new DefNode(
                $env,
                'user',
                Symbol::create('name'),
                TypeFactory::getInstance()->persistentHashMapFromKVs(
                    new Keyword('private'),
                    true
                ),
                new LiteralNode(
                    $env
                        ->withContext(NodeEnvironment::CONTEXT_EXPRESSION)
                        ->withDisallowRecurFrame()
                        ->withBoundTo('user\name')
                        ->withDefAllowed(false),
                    'any value'
                )
            ),
            $defNode
        );
    }

    public function testInvalidMeta(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Metadata must be a String, Keyword or Map');

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            1,
            'any value',
        ]);
        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, $env);
    }
}
