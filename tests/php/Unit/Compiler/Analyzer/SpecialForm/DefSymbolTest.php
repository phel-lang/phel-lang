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

    public function test_nested_def_is_allowed(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('outer'),
            Phel::list([
                Symbol::create(Symbol::NAME_DEF),
                Symbol::create('inner'),
                1,
            ]),
        ]);
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertSame('outer', $defNode->getName()->getName());
        self::assertInstanceOf(DefNode::class, $defNode->getInit());
        self::assertSame('inner', $defNode->getInit()->getName()->getName());
    }

    public function test_with_wrong_number_of_arguments(): void
    {
        $this->expectException(AnalyzerException::class);

        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
        ]);
        (new DefSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_def_without_value(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('baz'),
        ]);
        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, $env);

        self::assertSame('baz', $defNode->getName()->getName());
        self::assertInstanceOf(LiteralNode::class, $defNode->getInit());
        self::assertNull($defNode->getInit()->getValue());
    }

    public function test_first_argument_must_be_symbol(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("First argument of 'def must be a Symbol, got string");

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
                        ->withBoundTo('user\name'),
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
                        ->withBoundTo('user\name'),
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
                        ->withBoundTo('user\name'),
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
                        ->withBoundTo('user\name'),
                    'any value',
                ),
            ),
            $defNode,
        );
    }

    public function test_invalid_meta(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage('Metadata must be a String, Keyword, Map, got int');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            1,
            'any value',
        ]);
        $env = NodeEnvironment::empty();
        (new DefSymbol($this->analyzer))->analyze($list, $env);
    }

    public function test_arglists_for_single_arity_fn(): void
    {
        // (def my-fn (fn [x y] x))
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('my-fn'),
            Phel::list([
                Symbol::create(Symbol::NAME_FN),
                Phel::vector([
                    Symbol::create('x'),
                    Symbol::create('y'),
                ]),
                Symbol::create('x'),
            ]),
        ]);

        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, $env);

        self::assertSame('[x y]', $this->findMetaValue($defNode, 'arglists'));
    }

    public function test_arglists_for_variadic_fn(): void
    {
        // (def my-fn (fn [x & rest] x))
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('my-fn'),
            Phel::list([
                Symbol::create(Symbol::NAME_FN),
                Phel::vector([
                    Symbol::create('x'),
                    Symbol::create('&'),
                    Symbol::create('rest'),
                ]),
                Symbol::create('x'),
            ]),
        ]);

        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, $env);

        self::assertSame('[x & rest]', $this->findMetaValue($defNode, 'arglists'));
    }

    public function test_arglists_for_multi_arity_fn(): void
    {
        // (def f (fn ([] 1) ([x] x)))
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

        self::assertSame('([] [x])', $this->findMetaValue($defNode, 'arglists'));
    }

    public function test_arglists_for_zero_arity_fn(): void
    {
        // (def my-fn (fn [] 42))
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('my-fn'),
            Phel::list([
                Symbol::create(Symbol::NAME_FN),
                Phel::vector([]),
                42,
            ]),
        ]);

        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, $env);

        self::assertSame('[]', $this->findMetaValue($defNode, 'arglists'));
    }

    public function test_no_arglists_for_non_fn_def(): void
    {
        // (def name "any value")
        $list = Phel::list([
            Symbol::create(Symbol::NAME_DEF),
            Symbol::create('name'),
            'any value',
        ]);

        $env = NodeEnvironment::empty();
        $defNode = (new DefSymbol($this->analyzer))->analyze($list, $env);

        self::assertNull($this->findMetaValue($defNode, 'arglists'));
    }

    private function findMetaValue(DefNode $defNode, string $key): mixed
    {
        $meta = $defNode->getMeta()->getKeyValues();
        $counter = count($meta);
        for ($i = 0; $i < $counter; $i += 2) {
            $metaKey = $meta[$i];
            if ($metaKey instanceof LiteralNode && $metaKey->getValue() === $key) {
                $value = $meta[$i + 1];
                self::assertInstanceOf(LiteralNode::class, $value);

                return $value->getValue();
            }
        }

        return null;
    }
}
