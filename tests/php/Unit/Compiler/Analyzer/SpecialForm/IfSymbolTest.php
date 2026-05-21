<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Generator;
use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\IfSymbol;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\AbstractLocatedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IfSymbolTest extends TestCase
{
    #[DataProvider('providerRequiresAtLeastTwoOrThreeArgs')]
    public function test_requires_at_least_two_or_three_args(PersistentListInterface $list): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("'if requires two or three arguments");

        $this->analyze($list);
    }

    public static function providerRequiresAtLeastTwoOrThreeArgs(): Generator
    {
        yield 'No arguments provided: (if)' => [
            Phel::list([
                Symbol::create(Symbol::NAME_IF),
            ]),
        ];

        yield 'Only one argument provided: (if "one")' => [
            Phel::list([
                Symbol::create(Symbol::NAME_IF),
                'one',
            ]),
        ];

        yield 'Only one argument provided: (if "one" "two" "three" "four")' => [
            Phel::list([
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
        // The test is a `do` form so the constant folder leaves the IfNode
        // intact; pure literal tests are exercised in
        // {@see self::test_analyze_folds_literal_test()}.
        $list = Phel::list([
            Symbol::create(Symbol::NAME_IF),
            Phel::list([Symbol::create(Symbol::NAME_DO), true]),
            'then-expression',
            'else-expression',
        ]);

        $actual = $this->analyze($list);

        self::assertInstanceOf(IfNode::class, $actual);
        self::assertSame('then-expression', $actual->getThenExpr()->getValue());
        self::assertSame('else-expression', $actual->getElseExpr()->getValue());
        self::assertEquals(NodeEnvironment::empty(), $actual->getEnv());
    }

    public function test_analyze_folds_literal_truthy_test_to_then_branch(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_IF),
            true,
            'then-expression',
            'else-expression',
        ]);

        $folded = $this->analyze($list);

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame('then-expression', $folded->getValue());
    }

    public function test_analyze_folds_literal_falsy_test_to_else_branch(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_IF),
            false,
            'then-expression',
            'else-expression',
        ]);

        $folded = $this->analyze($list);

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame('else-expression', $folded->getValue());
    }

    private function analyze(PersistentListInterface $list): AbstractNode
    {
        $analyzer = new Analyzer(new GlobalEnvironment());

        return new IfSymbol($analyzer)->analyze($list, NodeEnvironment::empty());
    }
}
