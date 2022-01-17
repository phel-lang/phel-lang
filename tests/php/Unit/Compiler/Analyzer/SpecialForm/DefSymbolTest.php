<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\DefNode;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Ast\MapNode;
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

    public function test_with_def_not_allowed(): void
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

    public function test_with_wrong_number_of_arguments(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Two or three arguments are required for 'def. Got 2");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('1'),
        ]);
        (new DefSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_first_argument_must_be_symbol(): void
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

    public function test_false_init_value(): void
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

    public function test_init_values(): void
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
                new MapNode(
                    $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION),
                    []
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

    public function test_docstring(): void
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
                new MapNode(
                    $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION),
                    [
                        new LiteralNode(
                            $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION),
                            Keyword::create('doc')
                        ),
                        new LiteralNode(
                            $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION),
                            'my docstring'
                        ),
                    ]
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

    public function test_meta_keyword(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            Keyword::create('private'),
            'any value',
        ]);
        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, $env);

        self::assertEquals(
            new DefNode(
                $env,
                'user',
                Symbol::create('name'),
                new MapNode(
                    $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION),
                    [
                        new LiteralNode(
                            $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION),
                            Keyword::create('private')
                        ),
                        new LiteralNode(
                            $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION),
                            true
                        ),
                    ]
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

    public function test_meta_table_keyword(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('private'), true),
            'any value',
        ]);
        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, $env);

        self::assertEquals(
            new DefNode(
                $env,
                'user',
                Symbol::create('name'),
                new MapNode(
                    $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION),
                    [
                        new LiteralNode(
                            $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION),
                            Keyword::create('private'),
                            null
                        ),
                        new LiteralNode(
                            $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION),
                            true,
                            null
                        ),
                    ]
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

    public function test_invalid_meta(): void
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
