<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Generator;
use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\FnSymbol;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FnSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('phel\\core', Symbol::create('first'));
        $env->addDefinition('phel\\core', Symbol::create('next'));

        $this->analyzer = new Analyzer($env);
    }

    public function test_requires_at_least_one_arg(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("'fn requires at least one argument");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_FN),
        ]);

        (new FnSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_second_arg_must_be_a_vector(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Second argument of 'fn must be a vector");

        // This is the same as: (fn anything)
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FN),
            Symbol::create('anything'),
        ]);

        $this->analyze($list);
    }

    public function test_is_not_variadic(): void
    {
        // This is the same as: (fn [anything])
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FN),
            Phel::vector([
                Symbol::create('anything'),
            ]),
        ]);

        $fnNode = $this->analyze($list);

        self::assertFalse($fnNode->isVariadic());
    }

    #[DataProvider('providerVarNamesMustStartWithLetterOrUnderscore')]
    public function test_var_names_must_start_with_letter_or_underscore(string $paramName, bool $error): void
    {
        if ($error) {
            $this->expectException(AbstractLocatedException::class);
            $this->expectExceptionMessageMatches('/(Variable names must start with a letter or underscore)*/i');
        } else {
            self::assertTrue(true); // In order to have an assertion without an error
        }

        // This is the same as: (fn [paramName])
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FN),
            Phel::vector([
                Symbol::create($paramName),
            ]),
        ]);

        $this->analyze($list);
    }

    public static function providerVarNamesMustStartWithLetterOrUnderscore(): Generator
    {
        yield 'Start with a letter' => [
            'param-1',
            false,
        ];

        yield 'Start with an underscore' => [
            '_param-2',
            false,
        ];

        yield 'Start with a number' => [
            '1-param-3',
            true,
        ];

        yield 'Start with an ampersand' => [
            '&-param-4',
            true,
        ];

        yield 'Start with a space' => [
            ' param-5',
            true,
        ];
    }

    public function test_only_one_symbol_can_follow_the_ampersand_parameter(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('Unsupported parameter form, only one symbol can follow the & parameter');

        // This is the same as: (fn [& param-1 param-2])
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FN),
            Phel::vector([
                Symbol::create('&'),
                Symbol::create('param-1'),
                Symbol::create('param-2'),
            ]),
        ]);

        $this->analyze($list);
    }

    #[DataProvider('providerGetParams')]
    public function test_get_params(PersistentListInterface $list, array $expectedParams): void
    {
        $node = $this->analyze($list);

        self::assertEquals($expectedParams, $node->getParams());
    }

    public static function providerGetParams(): Generator
    {
        yield '(fn [& param-1])' => [
            Phel::list([
                Symbol::create(Symbol::NAME_FN),
                Phel::vector([
                    Symbol::create('&'),
                    Symbol::create('param-1'),
                ]),
            ]),
            [
                Symbol::create('param-1'),
            ],
        ];

        yield '(fn [param-1 param-2 param-3])' => [
            Phel::list([
                Symbol::create(Symbol::NAME_FN),
                Phel::vector([
                    Symbol::create('param-1'),
                    Symbol::create('param-2'),
                    Symbol::create('param-3'),
                ]),
            ]),
            [
                Symbol::create('param-1'),
                Symbol::create('param-2'),
                Symbol::create('param-3'),
            ],
        ];
    }

    #[DataProvider('providerGetBody')]
    public function test_get_body(PersistentListInterface $list, string $expectedBodyInstanceOf): void
    {
        $node = $this->analyze($list);

        self::assertInstanceOf($expectedBodyInstanceOf, $node->getBody());
    }

    public static function providerGetBody(): Generator
    {
        yield 'DoNode body => (fn [x] x)' => [
            Phel::list([
                Symbol::create(Symbol::NAME_FN),
                Phel::vector([
                    Symbol::create('x'),
                ]),
                Symbol::create('x'),
            ]),
            DoNode::class,
        ];

        yield 'LetNode body => (fn [[x y]] x)' => [
            Phel::list([
                Symbol::create(Symbol::NAME_FN),
                Phel::vector([
                    Phel::vector([
                        Symbol::create('x'),
                        Symbol::create('y'),
                    ]),
                ]),
                Symbol::create('x'),
            ]),
            LetNode::class,
        ];
    }

    public function test_multi_arity_returns_multi_fn_node(): void
    {
        $list = Phel::list([
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
        ]);

        $node = (new FnSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());

        self::assertInstanceOf(MultiFnNode::class, $node);
        self::assertCount(2, $node->getFnNodes());
        self::assertSame(0, $node->getMinArity());
    }

    public function test_variadic_overload_must_be_last(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('Variadic overload must be the last one');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_FN),
            Phel::list([
                Phel::vector([
                    Symbol::create('x'),
                    Symbol::create('&'),
                    Symbol::create('rest'),
                ]),
                Symbol::create('x'),
            ]),
            Phel::list([
                Phel::vector([
                    Symbol::create('y'),
                ]),
                Symbol::create('y'),
            ]),
        ]);

        (new FnSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_pre_condition_returning_falsy_is_ignored_when_disabled(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_FN),
            Phel::vector([
                Symbol::create('x'),
            ]),
            Phel::map(
                Phel::keyword('pre'),
                Phel::vector([false]),
            ),
            Symbol::create('x'),
        ]);

        $analyzer = new Analyzer(new GlobalEnvironment(), false);
        $node = (new FnSymbol($analyzer, false))->analyze($list, NodeEnvironment::empty());

        self::assertInstanceOf(DoNode::class, $node->getBody());
        self::assertSame([], $node->getBody()->getStmts());
        self::assertInstanceOf(LocalVarNode::class, $node->getBody()->getRet());
        self::assertSame('x', $node->getBody()->getRet()->getName()->getName());
    }

    private function analyze(PersistentListInterface $list): AbstractNode
    {
        return (new FnSymbol($this->analyzer))
            ->analyze($list, NodeEnvironment::empty());
    }
}
