<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Compiler\Analyzer\Analyzer;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\DoNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\DoSymbol;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class DoSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    public function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_wrong_symbol_name(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("This is not a 'do.");

        $list = TypeFactory::getInstance()->persistentListFromArray([Symbol::create('unknown')]);
        $env = NodeEnvironment::empty();
        (new DoSymbol($this->analyzer))->analyze($list, $env);
    }

    public function test_empty_list(): void
    {
        $env = NodeEnvironment::empty();
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DO),
        ]);

        $expected = new DoNode(
            $env,
            $stmts = [],
            $this->analyzer->analyze(null, $env),
            $list->getStartLocation()
        );

        $actual = (new DoSymbol($this->analyzer))->analyze($list, $env);
        self::assertEquals($expected, $actual);
    }

    public function test_with_one_scalar_value(): void
    {
        $env = NodeEnvironment::empty();

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DO),
            1,
        ]);

        $expected = new DoNode(
            $env,
            $stmts = [],
            $this->analyzer->analyze(1, $env),
            $list->getStartLocation()
        );

        $actual = (new DoSymbol($this->analyzer))->analyze($list, $env);
        self::assertEquals($expected, $actual);
    }

    public function test_with_two_scalar_value(): void
    {
        $env = NodeEnvironment::empty();

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DO),
            1,
            2,
        ]);

        $expected = new DoNode(
            $env,
            $stmts = [
                $this->analyzer->analyze(1, $env->withDisallowRecurFrame()),
            ],
            $this->analyzer->analyze(2, $env),
            $list->getStartLocation()
        );

        $actual = (new DoSymbol($this->analyzer))->analyze($list, $env);
        self::assertEquals($expected, $actual);
    }
}
