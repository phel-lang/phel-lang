<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\FnNode;
use Phel\Compiler\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter\FnAsClassEmitter;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter\MethodEmitter;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class FnAsClassEmitterTest extends TestCase
{
    private FnAsClassEmitter $fnAsClassEmitter;

    public function setUp(): void
    {
        $outputEmitter = (new CompilerFactory())
            ->createOutputEmitter();

        $this->fnAsClassEmitter = new FnAsClassEmitter($outputEmitter, new MethodEmitter($outputEmitter));
    }

    public function test_identity_fn(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            $params = [Symbol::create('x')],
            $body = PhpVarNode::withReturnContext('$x'),
            $uses = [],
            $isVariadic = false,
            $recurs = false
        );

        $this->fnAsClassEmitter->emit($fnNode);

        $this->expectOutputString('new class() extends \Phel\Lang\AbstractFn {
  public const BOUND_TO = "";

  public function __invoke($x) {
    return $x;
  }
};');
    }

    public function test_without_parameters(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            $params = [],
            $body = PhpVarNode::withReturnContext('$x'),
            $uses = [],
            $isVariadic = false,
            $recurs = false
        );

        $this->fnAsClassEmitter->emit($fnNode);

        $this->expectOutputString('new class() extends \Phel\Lang\AbstractFn {
  public const BOUND_TO = "";

  public function __invoke() {
    return $x;
  }
};');
    }

    public function test_with_uses(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            $params = [Symbol::create('x')],
            $body = PhpVarNode::withReturnContext('$x'),
            $uses = [Symbol::create('use1'), Symbol::create('use2')],
            $isVariadic = false,
            $recurs = false
        );

        $this->fnAsClassEmitter->emit($fnNode);

        $this->expectOutputString('new class($use1, $use2) extends \Phel\Lang\AbstractFn {
  public const BOUND_TO = "";
  private $use1;
  private $use2;

  public function __construct($use1, $use2) {
    $this->use1 = $use1;
    $this->use2 = $use2;
  }

  public function __invoke($x) {
    $use1 = $this->use1;
    $use2 = $this->use2;
    return $x;
  }
};');
    }

    public function test_is_variadic(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            $params = [Symbol::create('x')],
            $body = PhpVarNode::withReturnContext('$x'),
            $uses = [],
            $isVariadic = true,
            $recurs = false
        );

        $this->fnAsClassEmitter->emit($fnNode);

        $this->expectOutputString('new class() extends \Phel\Lang\AbstractFn {
  public const BOUND_TO = "";

  public function __invoke(...$x) {
    $x = \Phel\Lang\TypeFactory::getInstance()->persistentVectorFromArray($x);
    return $x;
  }
};');
    }

    public function test_is_recurs(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            $params = [Symbol::create('x')],
            $body = PhpVarNode::withReturnContext('$x'),
            $uses = [],
            $isVariadic = false,
            $recurs = true
        );

        $this->fnAsClassEmitter->emit($fnNode);

        $this->expectOutputString('new class() extends \Phel\Lang\AbstractFn {
  public const BOUND_TO = "";

  public function __invoke($x) {
    while (true) {
      return $x;break;
    }
  }
};');
    }

    public function test_variadic_and_recurs(): void
    {
        $fnNode = new FnNode(
            NodeEnvironment::empty(),
            $params = [Symbol::create('x')],
            $body = PhpVarNode::withReturnContext('$x'),
            $uses = [],
            $isVariadic = true,
            $recurs = true
        );

        $this->fnAsClassEmitter->emit($fnNode);

        $this->expectOutputString('new class() extends \Phel\Lang\AbstractFn {
  public const BOUND_TO = "";

  public function __invoke(...$x) {
    $x = \Phel\Lang\TypeFactory::getInstance()->persistentVectorFromArray($x);
    while (true) {
      return $x;break;
    }
  }
};');
    }
}
