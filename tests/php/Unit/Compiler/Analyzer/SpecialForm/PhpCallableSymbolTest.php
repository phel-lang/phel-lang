<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel;
use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpCallableNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\PhpCallableSymbol;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\AbstractLocatedException;
use PHPUnit\Framework\TestCase;

final class PhpCallableSymbolTest extends TestCase
{
    private AnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new Analyzer(new GlobalEnvironment());
    }

    public function test_free_function(): void
    {
        $node = $this->analyze([
            Symbol::create(Symbol::NAME_PHP_CALLABLE),
            Symbol::create('\strtoupper'),
        ]);

        self::assertNull($node->getTargetExpr());
        self::assertFalse($node->isStatic());
        self::assertSame('\strtoupper', $node->getName());
    }

    public function test_static_method(): void
    {
        $node = $this->analyze([
            Symbol::create(Symbol::NAME_PHP_CALLABLE),
            Symbol::create('\DateTimeImmutable'),
            Symbol::create('createFromFormat'),
        ]);

        self::assertTrue($node->isStatic());
        self::assertInstanceOf(PhpClassNameNode::class, $node->getTargetExpr());
        self::assertSame('createFromFormat', $node->getName());
    }

    public function test_instance_method(): void
    {
        $node = $this->analyze([
            Symbol::create(Symbol::NAME_PHP_CALLABLE),
            42,
            Symbol::create('process'),
        ]);

        self::assertFalse($node->isStatic());
        self::assertInstanceOf(LiteralNode::class, $node->getTargetExpr());
        self::assertSame('process', $node->getName());
    }

    public function test_too_few_arguments(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("One or two arguments are expected for 'php/callable'");

        $this->analyze([Symbol::create(Symbol::NAME_PHP_CALLABLE)]);
    }

    public function test_too_many_arguments(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("One or two arguments are expected for 'php/callable'");

        $this->analyze([
            Symbol::create(Symbol::NAME_PHP_CALLABLE),
            Symbol::create('\Foo'),
            Symbol::create('bar'),
            Symbol::create('baz'),
        ]);
    }

    public function test_free_function_must_be_symbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("First argument of 'php/callable' must be a Symbol");

        $this->analyze([
            Symbol::create(Symbol::NAME_PHP_CALLABLE),
            42,
        ]);
    }

    public function test_method_argument_must_be_symbol(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage("Method argument of 'php/callable' must be a Symbol");

        $this->analyze([
            Symbol::create(Symbol::NAME_PHP_CALLABLE),
            Symbol::create('\Foo'),
            42,
        ]);
    }

    private function analyze(array $elements): PhpCallableNode
    {
        $list = Phel::list($elements);

        return new PhpCallableSymbol($this->analyzer)
            ->analyze($list, NodeEnvironment::empty());
    }
}
