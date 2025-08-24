<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\MethodCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpObjectCallSymbol;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Lang\Symbol;
use PhelType;
use PHPUnit\Framework\TestCase;

final class PhpObjectCallSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_list_without_argument(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("At least two arguments are expected for 'php/::");

        $list = PhelType::persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL),
            Symbol::create('\\'),
        ]);

        (new PhpObjectCallSymbol($this->analyzer, isStatic: true))
            ->analyze($list, NodeEnvironment::empty());
    }

    public function test_list_with_wrong_symbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Argument 2 of 'php/->' must be a List or a Symbol");

        $list = PhelType::persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_CALL),
            Symbol::create('\\'),
            '',
        ]);
        (new PhpObjectCallSymbol($this->analyzer, isStatic: false))
            ->analyze($list, NodeEnvironment::empty());
    }

    public function test_is_static(): void
    {
        $list = PhelType::persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_CALL),
            Symbol::create('\\'),
            Symbol::create(''),
        ]);
        $objCallNode = (new PhpObjectCallSymbol($this->analyzer, $isStatic = true))
            ->analyze($list, NodeEnvironment::empty());
        self::assertSame($isStatic, $objCallNode->isStatic());
        self::assertInstanceOf(PhpClassNameNode::class, $objCallNode->getTargetExpr());
        self::assertInstanceOf(PropertyOrConstantAccessNode::class, $objCallNode->getCallExpr());
    }

    public function test_is_not_static(): void
    {
        $list = PhelType::persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_CALL),
            Symbol::create('\\'),
            Symbol::create(''),
        ]);

        $objCallNode = (new PhpObjectCallSymbol($this->analyzer, $isStatic = false))
            ->analyze($list, NodeEnvironment::empty());

        self::assertSame($isStatic, $objCallNode->isStatic());
        self::assertInstanceOf(PhpClassNameNode::class, $objCallNode->getTargetExpr());
        self::assertInstanceOf(PropertyOrConstantAccessNode::class, $objCallNode->getCallExpr());
    }

    public function test_list2nd_elem_is_list(): void
    {
        $list = PhelType::persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL),
            Symbol::create('\\'),
            PhelType::persistentListFromArray([Symbol::create(''), '', '']),
        ]);

        $objCallNode = (new PhpObjectCallSymbol($this->analyzer, isStatic: true))
            ->analyze($list, NodeEnvironment::empty());

        self::assertTrue($objCallNode->isMethodCall());
        self::assertInstanceOf(PhpClassNameNode::class, $objCallNode->getTargetExpr());
        self::assertInstanceOf(MethodCallNode::class, $objCallNode->getCallExpr());
    }

    public function test_list2nd_elem_is_symbol(): void
    {
        $list = PhelType::persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_CALL),
            Symbol::create('\\'),
            Symbol::create(''),
        ]);

        $objCallNode = (new PhpObjectCallSymbol($this->analyzer, isStatic: true))
            ->analyze($list, NodeEnvironment::empty());

        self::assertFalse($objCallNode->isMethodCall());
        self::assertInstanceOf(PhpClassNameNode::class, $objCallNode->getTargetExpr());
        self::assertInstanceOf(PropertyOrConstantAccessNode::class, $objCallNode->getCallExpr());
    }

    public function test_nested_calls(): void
    {
        $list = PhelType::persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_CALL),
            Symbol::create('\\'),
            Symbol::create('foo'),
            Symbol::create('bar'),
        ]);

        $objCallNode = (new PhpObjectCallSymbol($this->analyzer, isStatic: false))
            ->analyze($list, NodeEnvironment::empty());

        self::assertInstanceOf(PropertyOrConstantAccessNode::class, $objCallNode->getCallExpr());

        $inner = $objCallNode->getTargetExpr();
        self::assertInstanceOf(PhpObjectCallNode::class, $inner);
        self::assertInstanceOf(PropertyOrConstantAccessNode::class, $inner->getCallExpr());
    }
}
