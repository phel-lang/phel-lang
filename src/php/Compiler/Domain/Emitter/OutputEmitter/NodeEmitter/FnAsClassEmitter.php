<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Symbol;

use function assert;
use function count;

final readonly class FnAsClassEmitter implements NodeEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private MethodEmitter $methodEmitter,
        private ClosureEmitterHelper $closureHelper,
    ) {}

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof FnNode);

        if ($node->isDefinition()) {
            $this->emitAsClass($node);
        } else {
            $this->emitAsClosure($node);
        }
    }

    private function emitAsClosure(FnNode $node): void
    {
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('(function(', $node->getStartSourceLocation());

        $this->methodEmitter->emitParameters($node);
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        $this->emitUseClause($node);
        $this->outputEmitter->emitLine(' {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        $this->methodEmitter->emitBody($node);
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('})', $node->getStartSourceLocation());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    private function emitUseClause(FnNode $node): void
    {
        $uses = $node->getUses();
        if ($uses === []) {
            return;
        }

        $this->outputEmitter->emitStr(' use(', $node->getStartSourceLocation());
        foreach ($uses as $i => $use) {
            $shadowed = $node->getEnv()->getShadowed($use);
            $normalizedUse = $shadowed instanceof Symbol ? $shadowed : $use;
            $this->outputEmitter->emitStr('$' . $this->outputEmitter->mungeEncode($normalizedUse->getName()), $node->getStartSourceLocation());
            if ($i < count($uses) - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }

    private function emitAsClass(FnNode $node): void
    {
        $this->emitClassBegin($node);
        $this->emitProperties($node);
        $this->emitConstructor($node);
        $this->emitInvoke($node);
        $this->emitClassEnd($node);
    }

    private function emitClassBegin(FnNode $node): void
    {
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('new class(', $node->getStartSourceLocation());

        $this->closureHelper->emitConstructorArguments($node->getUses(), $node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitLine(') extends \Phel\Lang\AbstractFn {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
    }

    private function emitProperties(FnNode $node): void
    {
        $ns = addslashes($this->outputEmitter->mungeEncodeNs($node->getEnv()->getBoundTo()));
        $this->outputEmitter->emitLine('public const BOUND_TO = "' . $ns . '";', $node->getStartSourceLocation());

        $this->closureHelper->emitProperties($node->getUses(), $node->getEnv(), $node->getStartSourceLocation());
    }

    private function emitConstructor(FnNode $node): void
    {
        $this->closureHelper->emitConstructor($node->getUses(), $node->getEnv(), $node->getStartSourceLocation());

        $this->outputEmitter->emitLine();
    }

    private function emitInvoke(FnNode $node): void
    {
        $this->methodEmitter->emit('__invoke', $node);
    }

    private function emitClassEnd(FnNode $node): void
    {
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
