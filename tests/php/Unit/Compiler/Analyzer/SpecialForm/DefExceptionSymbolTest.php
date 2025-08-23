<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefExceptionNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\DefExceptionSymbol;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Lang\Symbol;
use Phel\Lang\Type;
use PHPUnit\Framework\TestCase;

final class DefExceptionSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_with_wrong_number_of_arguments(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Exact one argument is required for 'defexception");

        $list = Type::persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF_EXCEPTION),
            Symbol::create('A'),
            Symbol::create('B'),
        ]);

        (new DefExceptionSymbol($this->analyzer))
            ->analyze($list, NodeEnvironment::empty());
    }

    public function test_first_arg_is_not_symbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("First argument of 'defexception must be a Symbol.");

        $list = Type::persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF_EXCEPTION),
            'no-symbol',
        ]);

        (new DefExceptionSymbol($this->analyzer))
            ->analyze($list, NodeEnvironment::empty());
    }

    public function test_def_exception_symbol(): void
    {
        $list = Type::persistentListFromArray([
            Symbol::create(Symbol::NAME_DEF_EXCEPTION),
            Symbol::create('MyExc'),
        ]);

        $defExceptionNode = (new DefExceptionSymbol($this->analyzer))
            ->analyze($list, NodeEnvironment::empty());

        self::assertEquals(
            new DefExceptionNode(
                NodeEnvironment::empty(),
                'user',
                Symbol::create('MyExc'),
                new PhpClassNameNode(NodeEnvironment::empty(), Symbol::create('\\Exception')),
            ),
            $defExceptionNode,
        );
    }
}
