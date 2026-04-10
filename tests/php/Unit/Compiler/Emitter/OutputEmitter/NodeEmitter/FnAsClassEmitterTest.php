<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\ClosureEmitterHelper;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\FnAsClassEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\MethodEmitter;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class FnAsClassEmitterTest extends TestCase
{
    private FnAsClassEmitter $fnAsClassEmitter;

    protected function setUp(): void
    {
        $outputEmitter = (new CompilerFactory())
            ->createOutputEmitter();

        $this->fnAsClassEmitter = new FnAsClassEmitter(
            $outputEmitter,
            new MethodEmitter($outputEmitter),
            new ClosureEmitterHelper($outputEmitter),
        );
    }

    public function test_identity_fn(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            params: [Symbol::create('x')],
            body: PhpVarNode::withReturnContext('$x'),
            uses: [],
            isVariadic: false,
            recurs: false,
        );

        $this->fnAsClassEmitter->emit($fnNode);

        $this->expectOutputString('(function($x) {
  return $x;
});');
    }

    public function test_without_parameters(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            params: [],
            body: PhpVarNode::withReturnContext('$x'),
            uses: [],
            isVariadic: false,
            recurs: false,
        );

        $this->fnAsClassEmitter->emit($fnNode);

        $this->expectOutputString('(function() {
  return $x;
});');
    }

    public function test_with_uses(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            params: [Symbol::create('x')],
            body: PhpVarNode::withReturnContext('$x'),
            uses: [Symbol::create('use1'), Symbol::create('use2')],
            isVariadic: false,
            recurs: false,
        );

        $this->fnAsClassEmitter->emit($fnNode);

        $this->expectOutputString('(function($x) use($use1, $use2) {
  return $x;
});');
    }

    public function test_is_variadic(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            params: [Symbol::create('x')],
            body: PhpVarNode::withReturnContext('$x'),
            uses: [],
            isVariadic: true,
            recurs: false,
        );

        $this->fnAsClassEmitter->emit($fnNode);

        $this->expectOutputString('(function(...$x) {
  $x = \Phel::vector($x);
  return $x;
});');
    }

    public function test_is_recurs(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            params: [Symbol::create('x')],
            body: PhpVarNode::withReturnContext('$x'),
            uses: [],
            isVariadic: false,
            recurs: true,
        );

        $this->fnAsClassEmitter->emit($fnNode);

        $this->expectOutputString('(function($x) {
  while (true) {
    return $x;break;
  }
});');
    }

    public function test_variadic_and_recurs(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            params: [Symbol::create('x')],
            body: PhpVarNode::withReturnContext('$x'),
            uses: [],
            isVariadic: true,
            recurs: true,
        );

        $this->fnAsClassEmitter->emit($fnNode);

        $this->expectOutputString('(function(...$x) {
  $x = \Phel::vector($x);
  while (true) {
    return $x;break;
  }
});');
    }

    public function test_named_fn_still_emits_class(): void
    {
        $env = NodeEnvironment::empty()->withBoundTo('user\\my-fn');
        $fnNode = new FnNode(
            $env,
            params: [Symbol::create('x')],
            body: PhpVarNode::withReturnContext('$x'),
            uses: [],
            isVariadic: false,
            recurs: false,
        );

        $this->fnAsClassEmitter->emit($fnNode);

        $this->expectOutputString('new class() extends \Phel\Lang\AbstractFn {
  public const BOUND_TO = "user\\\\my_fn";

  public function __invoke($x) {
    return $x;
  }
};');
    }
}
