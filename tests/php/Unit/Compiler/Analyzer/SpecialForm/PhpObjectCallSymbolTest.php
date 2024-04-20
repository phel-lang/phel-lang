<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Transpiler\Domain\Analyzer\Analyzer;
use Phel\Transpiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\MethodCallNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PropertyOrConstantAccessNode;
use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpObjectCallSymbol;
use Phel\Transpiler\Domain\Exceptions\AbstractLocatedException;
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
        $this->expectExceptionMessage("Exactly two arguments are expected for 'php/::");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL),
            Symbol::create('\\'),
        ]);

        (new PhpObjectCallSymbol($this->analyzer, isStatic: true))
            ->analyze($list, NodeEnvironment::empty());
    }

    public function test_list_with_wrong_symbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Second argument of 'php/-> must be a List or a Symbol");

        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_CALL),
            Symbol::create('\\'),
            '',
        ]);
        (new PhpObjectCallSymbol($this->analyzer, isStatic: false))
            ->analyze($list, NodeEnvironment::empty());
    }

    public function test_is_static(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
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
        $list = TypeFactory::getInstance()->persistentListFromArray([
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
        $list = TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL),
            Symbol::create('\\'),
            TypeFactory::getInstance()->persistentListFromArray([Symbol::create(''), '', '']),
        ]);

        $objCallNode = (new PhpObjectCallSymbol($this->analyzer, isStatic: true))
            ->analyze($list, NodeEnvironment::empty());

        self::assertTrue($objCallNode->isMethodCall());
        self::assertInstanceOf(PhpClassNameNode::class, $objCallNode->getTargetExpr());
        self::assertInstanceOf(MethodCallNode::class, $objCallNode->getCallExpr());
    }

    public function test_list2nd_elem_is_symbol(): void
    {
        $list = TypeFactory::getInstance()->persistentListFromArray([
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
}
