<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

use function assert;

/**
 * Emits a PHP anonymous class for reify* expressions.
 *
 * Generates: new class($captured...) { properties; constructor; methods; }
 * Each method has access to captured locals via $this->property.
 */
final readonly class ReifyEmitter implements NodeEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private MethodEmitter $methodEmitter,
        private ClosureEmitterHelper $closureHelper,
    ) {}

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof ReifyNode);

        $uses = $node->getUses();
        $env = $node->getEnv();
        $loc = $node->getStartSourceLocation();

        $this->outputEmitter->emitContextPrefix($env, $loc);
        $this->outputEmitter->emitStr('new class(', $loc);

        $this->closureHelper->emitConstructorArguments($uses, $env, $loc);
        $this->outputEmitter->emitLine(') {', $loc);
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->enterClassScope();

        $this->closureHelper->emitProperties($uses, $env, $loc);
        $this->closureHelper->emitConstructor($uses, $env, $loc);

        $this->emitMethods($node);

        $this->outputEmitter->exitClassScope();
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitStr('}', $loc);
        $this->outputEmitter->emitContextSuffix($env, $loc);
    }

    private function emitMethods(ReifyNode $node): void
    {
        $methods = $node->getMethods();
        foreach ($methods as $i => $method) {
            if ($node->getUses() !== [] || $i > 0) {
                $this->outputEmitter->emitLine();
            }

            $this->methodEmitter->emit($method->getName()->getName(), $method->getFnNode());
        }
    }
}
