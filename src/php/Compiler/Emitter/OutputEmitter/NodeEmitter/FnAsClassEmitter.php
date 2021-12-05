<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\FnNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Emitter\OutputEmitterInterface;

final class FnAsClassEmitter implements NodeEmitterInterface
{
    private OutputEmitterInterface $outputEmitter;
    private MethodEmitter $methodEmitter;

    public function __construct(OutputEmitterInterface $emitter, MethodEmitter $methodEmitter)
    {
        $this->outputEmitter = $emitter;
        $this->methodEmitter = $methodEmitter;
    }

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof FnNode);

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

        $usesCount = count($node->getUses());
        foreach ($node->getUses() as $i => $use) {
            $loc = $use->getStartLocation();
            $normalizedUse = $node->getEnv()->getShadowed($use) ?: $use;
            $this->outputEmitter->emitPhpVariable($normalizedUse, $loc);

            if ($i < $usesCount - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitLine(') extends \Phel\Lang\AbstractFn {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
    }

    private function emitProperties(FnNode $node): void
    {
        $ns = addslashes($this->outputEmitter->mungeEncodeNs($node->getEnv()->getBoundTo()));
        $this->outputEmitter->emitLine('public const BOUND_TO = "' . $ns . '";', $node->getStartSourceLocation());

        foreach ($node->getUses() as $use) {
            $normalizedUse = $node->getEnv()->getShadowed($use) ?: $use;

            $this->outputEmitter->emitLine(
                'private $' . $this->outputEmitter->mungeEncode($normalizedUse->getName()) . ';',
                $node->getStartSourceLocation()
            );
        }
    }

    private function emitConstructor(FnNode $node): void
    {
        $usesCount = count($node->getUses());

        if ($usesCount) {
            $this->outputEmitter->emitLine();
            $this->outputEmitter->emitStr('public function __construct(', $node->getStartSourceLocation());

            // Constructor parameter
            foreach ($node->getUses() as $i => $use) {
                $normalizedUse = $node->getEnv()->getShadowed($use) ?: $use;

                $this->outputEmitter->emitPhpVariable($normalizedUse, $node->getStartSourceLocation());

                if ($i < $usesCount - 1) {
                    $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
                }
            }

            $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
            $this->outputEmitter->increaseIndentLevel();

            // Constructor assignment
            foreach ($node->getUses() as $use) {
                $normalizedUse = $node->getEnv()->getShadowed($use) ?: $use;
                $varName = $this->outputEmitter->mungeEncode($normalizedUse->getName());

                $this->outputEmitter->emitLine(
                    '$this->' . $varName . ' = $' . $varName . ';',
                    $node->getStartSourceLocation()
                );
            }

            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
        }

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
