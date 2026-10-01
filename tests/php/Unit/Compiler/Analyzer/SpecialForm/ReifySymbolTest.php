<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructMethod;
use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\InterfaceImplementationsAnalyzer;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\MethodBodyAnalyzer;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpBlockAnalyzer;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReifySymbol;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Munge;
use PHPUnit\Framework\TestCase;

use function array_map;

final class ReifySymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_with_no_methods(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("At least one method is required for 'reify*");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_REIFY),
        ]);

        $this->createSymbol()->analyze($list, NodeEnvironment::empty());
    }

    public function test_method_not_a_list(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('Each reify* method must be a list');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_REIFY),
            'not-a-list',
        ]);

        $this->createSymbol()->analyze($list, NodeEnvironment::empty());
    }

    public function test_method_name_not_symbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('Method name must be a Symbol');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_REIFY),
            Phel::list(['not-a-symbol', Phel::vector([Symbol::create('this')]), 'body']),
        ]);

        $this->createSymbol()->analyze($list, NodeEnvironment::empty());
    }

    public function test_method_args_not_vector(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('Method arguments must be a vector');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_REIFY),
            Phel::list([Symbol::create('greet'), 'not-a-vector', 'body']),
        ]);

        $this->createSymbol()->analyze($list, NodeEnvironment::empty());
    }

    public function test_method_missing_this_arg(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('Method must have at least one argument (this)');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_REIFY),
            Phel::list([Symbol::create('greet'), Phel::vector([]), 'body']),
        ]);

        $this->createSymbol()->analyze($list, NodeEnvironment::empty());
    }

    public function test_single_method(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_REIFY),
            Phel::list([
                Symbol::create('greet'),
                Phel::vector([Symbol::create('this')]),
                'hello',
            ]),
        ]);

        $node = $this->createSymbol()->analyze($list, NodeEnvironment::empty());

        self::assertInstanceOf(ReifyNode::class, $node);
        self::assertCount(1, $node->getMethods());
        self::assertSame('greet', $node->getMethods()[0]->getName()->getName());
        self::assertSame([], $node->getUses());
    }

    public function test_multiple_methods(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_REIFY),
            Phel::list([
                Symbol::create('greet'),
                Phel::vector([Symbol::create('this')]),
                'hello',
            ]),
            Phel::list([
                Symbol::create('farewell'),
                Phel::vector([Symbol::create('this')]),
                'bye',
            ]),
        ]);

        $node = $this->createSymbol()->analyze($list, NodeEnvironment::empty());

        self::assertCount(2, $node->getMethods());
        self::assertSame('greet', $node->getMethods()[0]->getName()->getName());
        self::assertSame('farewell', $node->getMethods()[1]->getName()->getName());
    }

    public function test_captures_uses_from_environment(): void
    {
        $env = NodeEnvironment::empty()
            ->withMergedLocals([Symbol::create('name')]);

        $list = Phel::list([
            Symbol::create(Symbol::NAME_REIFY),
            Phel::list([
                Symbol::create('greet'),
                Phel::vector([Symbol::create('this')]),
                Symbol::create('name'),
            ]),
        ]);

        $node = $this->createSymbol()->analyze($list, $env);

        self::assertCount(1, $node->getUses());
        self::assertSame('name', $node->getUses()[0]->getName());
    }

    public function test_deduplicates_uses_across_methods(): void
    {
        $env = NodeEnvironment::empty()
            ->withMergedLocals([Symbol::create('shared')]);

        $list = Phel::list([
            Symbol::create(Symbol::NAME_REIFY),
            Phel::list([
                Symbol::create('method-a'),
                Phel::vector([Symbol::create('this')]),
                Symbol::create('shared'),
            ]),
            Phel::list([
                Symbol::create('method-b'),
                Phel::vector([Symbol::create('this')]),
                Symbol::create('shared'),
            ]),
        ]);

        $node = $this->createSymbol()->analyze($list, $env);

        self::assertCount(1, $node->getUses(), 'shared variable should appear only once');
    }

    public function test_interface_methods_follow_the_interface_name(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_REIFY),
            Phel::list([Symbol::create('own'), Phel::vector([Symbol::create('this')]), 1]),
            Symbol::create('\\Countable'),
            Phel::list([Symbol::create('count'), Phel::vector([Symbol::create('this')]), 2]),
        ]);

        $node = $this->createSymbol()->analyze($list, NodeEnvironment::empty());

        self::assertSame(['\\Countable'], $node->getInterfaceNames());
        self::assertSame(['own', 'count'], array_map(
            static fn(DefStructMethod $method): string => $method->getName()->getName(),
            $node->getMethods(),
        ));
        self::assertNull($node->getMethods()[0]->getFnNode()->getReturnType());
        self::assertSame('int', $node->getMethods()[1]->getFnNode()->getReturnType());
    }

    public function test_a_method_listed_under_two_interfaces_is_rejected(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('reify defines method count more than once');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_REIFY),
            Symbol::create('\\Countable'),
            Phel::list([Symbol::create('count'), Phel::vector([Symbol::create('this')]), 1]),
            Symbol::create('\\Countable'),
            Phel::list([Symbol::create('count'), Phel::vector([Symbol::create('this')]), 2]),
        ]);

        $this->createSymbol()->analyze($list, NodeEnvironment::empty());
    }

    public function test_a_protocol_method_clashing_with_an_interface_method_is_rejected(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('reify defines method count more than once');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_REIFY),
            Phel::list([Symbol::create('Count'), Phel::vector([Symbol::create('this')]), 1]),
            Symbol::create('\\Countable'),
            Phel::list([Symbol::create('count'), Phel::vector([Symbol::create('this')]), 2]),
        ]);

        $this->createSymbol()->analyze($list, NodeEnvironment::empty());
    }

    public function test_unknown_interface_names_the_symbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('Can not resolve interface NoSuchInterface');

        $list = Phel::list([
            Symbol::create(Symbol::NAME_REIFY),
            Symbol::create('NoSuchInterface'),
            Phel::list([Symbol::create('m'), Phel::vector([Symbol::create('this')]), 1]),
        ]);

        $this->createSymbol()->analyze($list, NodeEnvironment::empty());
    }

    private function createSymbol(): ReifySymbol
    {
        $munge = new Munge();

        return new ReifySymbol(
            new MethodBodyAnalyzer($this->analyzer),
            new InterfaceImplementationsAnalyzer(
                $this->analyzer,
                $munge,
                new MethodBodyAnalyzer($this->analyzer),
                new PhpBlockAnalyzer($munge, new MethodBodyAnalyzer($this->analyzer)),
            ),
        );
    }
}
