<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Transpiler\Domain\Analyzer\Analyzer;
use Phel\Transpiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\DefNode;
use Phel\Transpiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Transpiler\Domain\Analyzer\Ast\MapNode;
use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefSymbol;
use PHPUnit\Framework\TestCase;
use stdClass;

final class DefSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
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
                    $env->withExpressionContext(),
                    [],
                ),
                new LiteralNode(
                    $env
                        ->withExpressionContext()
                        ->withDisallowRecurFrame()
                        ->withBoundTo('user\name')
                        ->withDefAllowed(false),
                    'any value',
                ),
            ),
            $defNode,
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
                    $env->withExpressionContext(),
                    [
                        new LiteralNode(
                            $env->withExpressionContext(),
                            Keyword::create('doc'),
                        ),
                        new LiteralNode(
                            $env->withExpressionContext(),
                            'my docstring',
                        ),
                    ],
                ),
                new LiteralNode(
                    $env
                        ->withExpressionContext()
                        ->withDisallowRecurFrame()
                        ->withBoundTo('user\name')
                        ->withDefAllowed(false),
                    'any value',
                ),
            ),
            $defNode,
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
                    $env->withExpressionContext(),
                    [
                        new LiteralNode(
                            $env->withExpressionContext(),
                            Keyword::create('private'),
                        ),
                        new LiteralNode(
                            $env->withExpressionContext(),
                            true,
                        ),
                    ],
                ),
                new LiteralNode(
                    $env
                        ->withExpressionContext()
                        ->withDisallowRecurFrame()
                        ->withBoundTo('user\name')
                        ->withDefAllowed(false),
                    'any value',
                ),
            ),
            $defNode,
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
                    $env->withExpressionContext(),
                    [
                        new LiteralNode(
                            $env->withExpressionContext(),
                            Keyword::create('private'),
                            null,
                        ),
                        new LiteralNode(
                            $env->withExpressionContext(),
                            true,
                            null,
                        ),
                    ],
                ),
                new LiteralNode(
                    $env
                        ->withExpressionContext()
                        ->withDisallowRecurFrame()
                        ->withBoundTo('user\name')
                        ->withDefAllowed(false),
                    'any value',
                ),
            ),
            $defNode,
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
        (new DefSymbol($this->analyzer))->analyze($list, $env);
    }
}
