<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Generator;
use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\IfSymbol;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
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
            Phel::persistentListFromArray([
                Symbol::create(Symbol::NAME_IF),
            ]),
        ];

        yield 'Only one argument provided: (if "one")' => [
            Phel::persistentListFromArray([
                Symbol::create(Symbol::NAME_IF),
                'one',
            ]),
        ];

        yield 'Only one argument provided: (if "one" "two" "three" "four")' => [
            Phel::persistentListFromArray([
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
        $list = Phel::persistentListFromArray([
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
