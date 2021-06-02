<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Generator;
use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\DoNode;
use Phel\Compiler\Analyzer\Ast\FnNode;
use Phel\Compiler\Analyzer\Ast\LetNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\FnSymbol;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class FnSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    public function setUp(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('phel\\core', Symbol::create('first'), TypeFactory::getInstance()->emptyPersistentMap());
        $env->addDefinition('phel\\core', Symbol::create('next'), TypeFactory::getInstance()->emptyPersistentMap());
        $this->analyzer = new Analyzer($env);
    }

    public function test_requires_at_least_one_arg(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("'fn requires at least one argument");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FN),
        ]);

        (new FnSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_second_arg_must_be_a_vector(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Second argument of 'fn must be a vector");

        // This is the same as: (fn anything)
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FN),
            Symbol::create('anything'),
        ]);

        $this->analyze($list);
    }

    public function test_is_not_variadic(): void
    {
        // This is the same as: (fn [anything])
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FN),
            TypeFactory::getInstance()->persistentVectorFromArray([
                Symbol::create('anything'),
            ]),
        ]);

        $fnNode = $this->analyze($list);

        self::assertFalse($fnNode->isVariadic());
    }

    /**
     * @dataProvider providerVarNamesMustStartWithLetterOrUnderscore
     */
    public function test_var_names_must_start_with_letter_or_underscore(string $paramName, bool $error): void
    {
        if ($error) {
            $this->expectException(AbstractLocatedException::class);
            $this->expectExceptionMessageMatches('/(Variable names must start with a letter or underscore)*/i');
        } else {
            self::assertTrue(true); // In order to have an assertion without an error
        }

        // This is the same as: (fn [paramName])
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FN),
            TypeFactory::getInstance()->persistentVectorFromArray([
                Symbol::create($paramName),
            ]),
        ]);

        $this->analyze($list);
    }

    public function providerVarNamesMustStartWithLetterOrUnderscore(): Generator
    {
        yield 'Start with a letter' => [
            'paramName' => 'param-1',
            'error' => false,
        ];

        yield 'Start with an underscore' => [
            'paramName' => '_param-2',
            'error' => false,
        ];

        yield 'Start with a number' => [
            'paramName' => '1-param-3',
            'error' => true,
        ];

        yield 'Start with an ampersand' => [
            'paramName' => '&-param-4',
            'error' => true,
        ];

        yield 'Start with a space' => [
            'paramName' => ' param-5',
            'error' => true,
        ];
    }

    public function test_only_one_symbol_can_follow_the_ampersand_parameter(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('Unsupported parameter form, only one symbol can follow the & parameter');

        // This is the same as: (fn [& param-1 param-2])
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_FN),
            TypeFactory::getInstance()->persistentVectorFromArray([
                Symbol::create('&'),
                Symbol::create('param-1'),
                Symbol::create('param-2'),
            ]),
        ]);

        $this->analyze($list);
    }

    /**
     * @dataProvider providerGetParams
     */
    public function test_get_params(PersistentListInterface $list, array $expectedParams): void
    {
        $node = $this->analyze($list);

        self::assertEquals($expectedParams, $node->getParams());
    }

    public function providerGetParams(): Generator
    {
        yield '(fn [& param-1])' => [
            'list' => TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_FN),
                TypeFactory::getInstance()->persistentVectorFromArray([
                    Symbol::create('&'),
                    Symbol::create('param-1'),
                ]),
            ]),
            'expectedParams' => [
                Symbol::create('param-1'),
            ],
        ];

        yield '(fn [param-1 param-2 param-3])' => [
            'list' => TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_FN),
                TypeFactory::getInstance()->persistentVectorFromArray([
                    Symbol::create('param-1'),
                    Symbol::create('param-2'),
                    Symbol::create('param-3'),
                ]),
            ]),
            'expectedParams' => [
                Symbol::create('param-1'),
                Symbol::create('param-2'),
                Symbol::create('param-3'),
            ],
        ];
    }

    /**
     * @dataProvider providerGetBody
     */
    public function test_get_body(PersistentListInterface $list, string $expectedBodyInstanceOf): void
    {
        $node = $this->analyze($list);

        self::assertInstanceOf($expectedBodyInstanceOf, $node->getBody());
    }

    public function providerGetBody(): Generator
    {
        yield 'DoNode body => (fn [x] x)' => [
            'list' => TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_FN),
                TypeFactory::getInstance()->persistentVectorFromArray([
                    Symbol::create('x'),
                ]),
                Symbol::create('x'),
            ]),
            'expectedBodyInstanceOf' => DoNode::class,
        ];

        yield 'LetNode body => (fn [[x y]] x)' => [
            'list' => TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_FN),
                TypeFactory::getInstance()->persistentVectorFromArray([
                    TypeFactory::getInstance()->persistentVectorFromArray([
                        Symbol::create('x'),
                        Symbol::create('y'),
                    ]),
                ]),
                Symbol::create('x'),
            ]),
            'expectedBodyInstanceOf' => LetNode::class,
        ];
    }

    private function analyze(PersistentListInterface $list): FnNode
    {
        return (new FnSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }
}
