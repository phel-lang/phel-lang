<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefSymbol;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;
use stdClass;

use function count;

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

        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            Phel::list([
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

        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('1'),
        ]);
        (new DefSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_first_argument_must_be_symbol(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("First argument of 'def must be a Symbol.");

        $list = Phel::list([
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

        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            new stdClass(),
        ]);
        (new DefSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_init_values(): void
    {
        $list = Phel::list([
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
        $list = Phel::list([
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
        $list = Phel::list([
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

    public function test_min_arity_from_multi_fn(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('f'),
            Phel::list([
                Symbol::create(Symbol::NAME_FN),
                Phel::list([
                    Phel::vector([]),
                    1,
                ]),
                Phel::list([
                    Phel::vector([
                        Symbol::create('x'),
                    ]),
                    Symbol::create('x'),
                ]),
            ]),
        ]);

        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, $env);

        $meta = $defNode->getMeta()->getKeyValues();
        $found = false;
        $counter = count($meta);
        for ($i = 0; $i < $counter; $i += 2) {
            $key = $meta[$i];
            $value = $meta[$i + 1];
            if ($key instanceof LiteralNode && $key->getValue() === 'min-arity') {
                self::assertInstanceOf(LiteralNode::class, $value);
                self::assertSame(0, $value->getValue());
                $found = true;
            }
        }

        self::assertTrue($found, 'min-arity not found');
    }

    public function test_meta_table_keyword(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            Phel::map(Keyword::create('private'), true),
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

        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            1,
            'any value',
        ]);
        $env = NodeEnvironment::empty();
        (new DefSymbol($this->analyzer))->analyze($list, $env);
    }
}
