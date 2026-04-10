<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Symbol;

use function assert;
use function count;

final readonly class ReifyEmitter implements NodeEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private MethodEmitter $methodEmitter,
    ) {}

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof ReifyNode);

        $this->emitClassBegin($node);
        $this->emitProperties($node);
        $this->emitConstructor($node);
        $this->emitMethods($node);
        $this->emitClassEnd($node);
    }

    private function emitClassBegin(ReifyNode $node): void
    {
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('new class(', $node->getStartSourceLocation());

        $usesCount = count($node->getUses());
        foreach ($node->getUses() as $i => $use) {
            $loc = $use->getStartLocation();
            /** @var Symbol $normalizedUse */
            $normalizedUse = $node->getEnv()->getShadowed($use) instanceof Symbol
                ? $node->getEnv()->getShadowed($use)
                : $use;
            $this->outputEmitter->emitPhpVariable($normalizedUse, $loc);

            if ($i < $usesCount - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
    }

    private function emitProperties(ReifyNode $node): void
    {
        foreach ($node->getUses() as $use) {
            /** @var Symbol $normalizedUse */
            $normalizedUse = $node->getEnv()->getShadowed($use) instanceof Symbol
                ? $node->getEnv()->getShadowed($use)
                : $use;
            $this->outputEmitter->emitLine(
                'private $' . $this->outputEmitter->mungeEncode($normalizedUse->getName()) . ';',
                $node->getStartSourceLocation(),
            );
        }
    }

    private function emitConstructor(ReifyNode $node): void
    {
        $usesCount = count($node->getUses());

        if ($usesCount !== 0) {
            $this->outputEmitter->emitLine();
            $this->outputEmitter->emitStr('public function __construct(', $node->getStartSourceLocation());

            foreach ($node->getUses() as $i => $use) {
                /** @var Symbol $normalizedUse */
                $normalizedUse = $node->getEnv()->getShadowed($use) instanceof Symbol
                    ? $node->getEnv()->getShadowed($use)
                    : $use;

                $this->outputEmitter->emitPhpVariable($normalizedUse, $node->getStartSourceLocation());

                if ($i < $usesCount - 1) {
                    $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
                }
            }

            $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
            $this->outputEmitter->increaseIndentLevel();

            foreach ($node->getUses() as $use) {
                /** @var Symbol $normalizedUse */
                $normalizedUse = $node->getEnv()->getShadowed($use) instanceof Symbol
                    ? $node->getEnv()->getShadowed($use)
                    : $use;
                $varName = $this->outputEmitter->mungeEncode($normalizedUse->getName());

                $this->outputEmitter->emitLine(
                    '$this->' . $varName . ' = $' . $varName . ';',
                    $node->getStartSourceLocation(),
                );
            }

            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitLine();
    }

    private function emitMethods(ReifyNode $node): void
    {
        foreach ($node->getMethods() as $method) {
            $this->methodEmitter->emit($method->getName()->getName(), $method->getFnNode());
        }
    }

    private function emitClassEnd(ReifyNode $node): void
    {
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
