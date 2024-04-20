<?php

declare(strict_types=1);

namespace PhelTest\Unit\Transpiler\Analyzer\SpecialForm;

use Generator;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Transpiler\Domain\Analyzer\Analyzer;
use Phel\Transpiler\Domain\Analyzer\Ast\IfNode;
use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\IfSymbol;
use Phel\Transpiler\Domain\Exceptions\AbstractLocatedException;
use PHPUnit\Framework\TestCase;

final class IfSymbolTest extends TestCase
{
    /**
     * @dataProvider providerRequiresAtLeastTwoOrThreeArgs
     */
    public function test_requires_at_least_two_or_three_args(PersistentListInterface $list): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("'if requires two or three arguments");

        $this->analyze($list);
    }

    public function providerRequiresAtLeastTwoOrThreeArgs(): Generator
    {
        yield 'No arguments provided: (if)' => [
            'list' => TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_IF),
            ]),
        ];

        yield 'Only one argument provided: (if "one")' => [
            'list' => TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_IF),
                'one',
            ]),
        ];

        yield 'Only one argument provided: (if "one" "two" "three" "four")' => [
            'list' => TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_IF),
                'one',
                'two',
                'three',
                'four',
            ]),
        ];
    }

    public function test_analyze(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_IF),
            true,
            'then-expression',
            'else-expression',
        ]);

        $actual = $this->analyze($list);

        self::assertTrue($actual->getTestExpr()->getValue());
        self::assertSame('then-expression', $actual->getThenExpr()->getValue());
        self::assertSame('else-expression', $actual->getElseExpr()->getValue());
        self::assertEquals(NodeEnvironment::empty(), $actual->getEnv());
    }

    private function analyze(PersistentListInterface $list): IfNode
    {
        $analyzer = new Analyzer(new GlobalEnvironment());

        return (new IfSymbol($analyzer))->analyze($list, NodeEnvironment::empty());
    }
}
