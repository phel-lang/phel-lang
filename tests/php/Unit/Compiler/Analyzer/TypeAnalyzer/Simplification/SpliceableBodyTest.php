<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer\Simplification;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpRefNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\SpliceableBody;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A literal `fn` body spliced by the `update` lowering (#3322) runs in the
 * enclosing PHP frame instead of its own closure. Only an allowlist of
 * frame-independent, non-writing shapes may be spliced.
 */
final class SpliceableBodyTest extends TestCase
{
    private static NodeEnvironmentInterface $env;

    public static function setUpBeforeClass(): void
    {
        self::$env = NodeEnvironment::empty()->withExpressionContext();
    }

    #[DataProvider('providerSpliceable')]
    public function test_spliceable(AbstractNode $node): void
    {
        self::assertTrue(new SpliceableBody()->isSpliceable($node));
    }

    public static function providerSpliceable(): iterable
    {
        self::setUpBeforeClass();

        yield 'a local' => [self::local('v')];
        yield 'an operator' => [self::call(self::php('+'), self::local('v'), self::literal(1))];
        yield 'php/max' => [self::call(self::php('max'), self::literal(0.0), self::local('v'))];
        yield 'a keyword lookup' => [self::call(self::literal(Keyword::create('n')), self::local('v'))];
        yield 'an offset read' => [new PhpArrayGetNode(self::$env, self::local('arr'), [self::literal(0)])];
    }

    #[DataProvider('providerNotSpliceable')]
    public function test_not_spliceable(AbstractNode $node): void
    {
        self::assertFalse(new SpliceableBody()->isSpliceable($node));
    }

    public static function providerNotSpliceable(): iterable
    {
        self::setUpBeforeClass();

        yield 'php/=' => [self::call(self::php('='), self::local('flag'), self::literal(99))];
        yield 'php/=&' => [self::call(self::php('=&'), self::local('flag'), self::local('other'))];
        yield 'a by-reference php function' => [self::call(self::php('sort'), self::local('arr'))];
        yield 'func_get_args' => [self::call(self::php('func_get_args'))];
        yield 'extract' => [self::call(self::php('extract'), self::local('arr'))];
        yield 'php/aset' => [new PhpArraySetNode(self::$env, self::local('arr'), [self::literal(0)], self::literal(1))];
        yield 'php/ref' => [new PhpRefNode(self::$env, self::local('arr'))];
        yield 'a call through a local' => [self::call(self::local('f'), self::local('v'))];
        yield 'an undefined global' => [self::call(new GlobalVarNode(self::$env, 'user', Symbol::create('not-defined-yet'), Phel::map()), self::local('v'))];
        yield 'a vector with reader metadata' => [new VectorNode(self::$env, [self::local('v')], null, new MapNode(self::$env, [self::literal(Keyword::create('m')), self::local('v')]))];
        yield 'a dynamic global' => [self::call(new GlobalVarNode(self::$env, 'phel.core', Symbol::create('inc'), Phel::map(Keyword::create('dynamic'), true)), self::local('v'))];
        yield 'a redef global' => [self::call(new GlobalVarNode(self::$env, 'phel.core', Symbol::create('inc'), Phel::map(Keyword::create('redef'), true)), self::local('v'))];
        yield 'a pure call around an unsafe argument' => [self::call(self::php('max'), self::call(self::php('func_get_args')))];
    }

    private static function local(string $name): LocalVarNode
    {
        return new LocalVarNode(self::$env, Symbol::create($name));
    }

    private static function literal(mixed $value): LiteralNode
    {
        return new LiteralNode(self::$env, $value);
    }

    private static function php(string $name): PhpVarNode
    {
        return new PhpVarNode(self::$env, $name);
    }

    private static function call(AbstractNode $fn, AbstractNode ...$args): CallNode
    {
        return new CallNode(self::$env, $fn, $args);
    }
}
