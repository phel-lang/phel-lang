<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\CatchNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\TryNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\TrySymbol;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class TrySymbolTest extends TestCase
{
    public function test_requires_symbol_as_first_argument_of_catch(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("First argument of 'catch must be a Symbol, got string");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_TRY),
            Phel::list([Symbol::create(Symbol::NAME_QUOTE), 1]),
            Phel::list([
                Symbol::create('catch'),
                'not-symbol',
                Symbol::create('e'),
                2,
            ]),
        ]);

        $this->analyze($list);
    }

    public function test_requires_symbol_as_second_argument_of_catch(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Second argument of 'catch must be a Symbol, got string");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_TRY),
            Phel::list([Symbol::create(Symbol::NAME_QUOTE), 1]),
            Phel::list([
                Symbol::create('catch'),
                Symbol::create('\\Exception'),
                'not-symbol',
                2,
            ]),
        ]);

        $this->analyze($list);
    }

    public function test_invalid_try_form_in_catches_section(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Invalid 'try form");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_TRY),
            Phel::list([Symbol::create(Symbol::NAME_QUOTE), 1]),
            Phel::list([
                Symbol::create('catch'),
                Symbol::create('\\Exception'),
                Symbol::create('e'),
                2,
            ]),
            Phel::list([Symbol::create('unknown')]),
        ]);

        $this->analyze($list);
    }

    public function test_unexpected_form_after_finally(): void
    {
        $this->expectException(AnalyzerException::class);
        $this->expectExceptionMessage("Unexpected form after 'finally");

        $list = Phel::list([
            Symbol::create(Symbol::NAME_TRY),
            Phel::list([Symbol::create(Symbol::NAME_QUOTE), 1]),
            Phel::list([Symbol::create('finally'), 3]),
            Phel::list([
                Symbol::create('catch'),
                Symbol::create('\\Exception'),
                Symbol::create('e'),
                2,
            ]),
        ]);

        $this->analyze($list);
    }

    public function test_analyze_try_catch_finally(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_TRY),
            Phel::list([Symbol::create(Symbol::NAME_QUOTE), 1]),
            Phel::list([
                Symbol::create('catch'),
                Symbol::create('\\Exception'),
                Symbol::create('e'),
                2,
            ]),
            Phel::list([Symbol::create('finally'), 3]),
        ]);

        $actual = $this->analyze($list);

        self::assertInstanceOf(DoNode::class, $actual->getBody());
        self::assertCount(1, $actual->getCatches());

        $catchNode = $actual->getCatches()[0];
        self::assertInstanceOf(CatchNode::class, $catchNode);
        self::assertInstanceOf(PhpClassNameNode::class, $catchNode->getType());
        self::assertSame('e', $catchNode->getName()->getName());
        self::assertInstanceOf(DoNode::class, $catchNode->getBody());

        $finallyNode = $actual->getFinally();
        self::assertInstanceOf(DoNode::class, $finallyNode);

        self::assertEquals(NodeEnvironment::empty(), $actual->getEnv());
    }

    public function test_analyze_try_with_only_body(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_TRY),
            Phel::list([Symbol::create(Symbol::NAME_QUOTE), 1]),
            Phel::list([Symbol::create(Symbol::NAME_QUOTE), 2]),
        ]);

        $actual = $this->analyze($list);

        self::assertInstanceOf(TryNode::class, $actual);
        self::assertInstanceOf(DoNode::class, $actual->getBody());
        self::assertCount(0, $actual->getCatches());
        self::assertNull($actual->getFinally());
    }

    public function test_analyze_try_with_multiple_catches(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_TRY),
            Phel::list([Symbol::create(Symbol::NAME_QUOTE), 1]),
            Phel::list([
                Symbol::create('catch'),
                Symbol::create('\\Exception'),
                Symbol::create('e'),
                2,
            ]),
            Phel::list([
                Symbol::create('catch'),
                Symbol::create('\\RuntimeException'),
                Symbol::create('e2'),
                3,
            ]),
            Phel::list([
                Symbol::create('catch'),
                Symbol::create('\\LogicException'),
                Symbol::create('e3'),
                4,
            ]),
        ]);

        $actual = $this->analyze($list);

        self::assertInstanceOf(TryNode::class, $actual);
        self::assertCount(3, $actual->getCatches());

        self::assertSame('e', $actual->getCatches()[0]->getName()->getName());
        self::assertSame('e2', $actual->getCatches()[1]->getName()->getName());
        self::assertSame('e3', $actual->getCatches()[2]->getName()->getName());
    }

    public function test_analyze_try_with_only_catch_no_body(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_TRY),
            Phel::list([
                Symbol::create('catch'),
                Symbol::create('\\Exception'),
                Symbol::create('e'),
                2,
            ]),
        ]);

        $actual = $this->analyze($list);

        self::assertInstanceOf(TryNode::class, $actual);
        self::assertCount(1, $actual->getCatches());
    }

    public function test_analyze_try_with_only_finally(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_TRY),
            Phel::list([Symbol::create(Symbol::NAME_QUOTE), 1]),
            Phel::list([Symbol::create('finally'), 3]),
        ]);

        $actual = $this->analyze($list);

        self::assertInstanceOf(TryNode::class, $actual);
        self::assertCount(0, $actual->getCatches());
        self::assertInstanceOf(DoNode::class, $actual->getFinally());
    }

    public function test_analyze_try_catch_with_multiple_body_expressions(): void
    {
        $list = Phel::list([
            Symbol::create(Symbol::NAME_TRY),
            Phel::list([Symbol::create(Symbol::NAME_QUOTE), 1]),
            Phel::list([Symbol::create(Symbol::NAME_QUOTE), 2]),
            Phel::list([Symbol::create(Symbol::NAME_QUOTE), 3]),
            Phel::list([
                Symbol::create('catch'),
                Symbol::create('\\Exception'),
                Symbol::create('e'),
                4,
            ]),
        ]);

        $actual = $this->analyze($list);

        self::assertInstanceOf(TryNode::class, $actual);
        self::assertInstanceOf(DoNode::class, $actual->getBody());
        self::assertCount(1, $actual->getCatches());
    }

    private function analyze(PersistentListInterface $list): TryNode
    {
        $analyzer = new Analyzer(new GlobalEnvironment());

        return (new TrySymbol($analyzer))->analyze($list, NodeEnvironment::empty());
    }
}
