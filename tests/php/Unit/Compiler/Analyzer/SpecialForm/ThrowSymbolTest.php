<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ThrowSymbol;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class ThrowSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_requires_exactly_one_argument(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Exact one argument is required for 'throw");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_THROW),
        ]);

        (new ThrowSymbol($this->analyzer))->analyze($list, NodeEnvironment::empty());
    }

    public function test_throw_node(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_THROW),
            1,
        ]);
        $env = NodeEnvironment::empty();

        $expected = new ThrowNode(
            $env,
            $this->analyzer->analyze(1, $env->withExpressionContext()->withDisallowRecurFrame()),
            $list->getStartLocation(),
        );

        $actual = (new ThrowSymbol($this->analyzer))->analyze($list, $env);

        self::assertEquals($expected, $actual);
    }
}
