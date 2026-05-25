<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\IterableTarget;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Seq;

use function array_slice;
use function assert;
use function count;
use function is_int;

final class ApplyEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof ApplyNode);
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $fnNode = $node->getFn();

        if ($fnNode instanceof PhpVarNode && $fnNode->isInfix()) {
            $this->phpVarNodeAndFnNodeIsInfix($node, $fnNode);
        } elseif ($fnNode instanceof PhpVarNode) {
            $this->phpVarNodeButNoInfix($node, $fnNode);
        } else {
            $this->noPhpVarNode($node);
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    private function phpVarNodeAndFnNodeIsInfix(ApplyNode $node, PhpVarNode $fnNode): void
    {
        $this->outputEmitter->emitStr('array_reduce([', $node->getStartSourceLocation());
        $this->emitArguments($node);
        $this->outputEmitter->emitStr('], function($a, $b) { return ($a ', $node->getStartSourceLocation());
        $this->outputEmitter->emitStr($fnNode->getName(), $fnNode->getStartSourceLocation());
        $this->outputEmitter->emitStr(' $b); })', $node->getStartSourceLocation());
    }

    private function emitArguments(ApplyNode $node): void
    {
        $arguments = $node->getArguments();
        $lastIndex = count($arguments) - 1;

        foreach ($arguments as $i => $arg) {
            if ($i === $lastIndex) {
                $this->emitFinalArgument($node, $arg);
                continue;
            }

            $this->outputEmitter->emitNode($arg);
            $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
        }
    }

    private function emitFinalArgument(ApplyNode $node, AbstractNode $arg): void
    {
        // PHP `...$arr` spread already accepts native arrays positionally,
        // so when the analyzer has proven the final arg is a `(php/array …)`
        // result we drop the `Seq::toApplyArguments` walk entirely.
        if (IterableTarget::isPhpArray($arg)) {
            $this->outputEmitter->emitStr('...', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($arg);
            return;
        }

        $this->outputEmitter->emitStr('...\\' . Seq::class . '::toApplyArguments(', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($arg);
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }

    private function phpVarNodeButNoInfix(ApplyNode $node, PhpVarNode $fnNode): void
    {
        $this->outputEmitter->emitStr($fnNode->getAbsoluteName(), $fnNode->getStartSourceLocation());
        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $this->emitArguments($node);
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }

    private function noPhpVarNode(ApplyNode $node): void
    {
        if ($this->tryEmitDirectPositionalApply($node)) {
            return;
        }

        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getFn());
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $this->emitArguments($node);
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }

    /**
     * `(apply f [a b c])` where `f` is a fixed-arity `GlobalVarNode`
     * whose declared `min-arity` matches `#leading + #vec-elements`
     * lowers to a positional invocation. The vector literal is
     * unfolded into individual arguments, skipping the
     * `Seq::toApplyArguments` walk that the generic spread path
     * would emit.
     *
     * Skips variadic fns (`is-variadic`) and any final argument that
     * is not a syntactic `VectorNode`.
     */
    private function tryEmitDirectPositionalApply(ApplyNode $node): bool
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return false;
        }

        $arguments = $node->getArguments();
        if ($arguments === []) {
            return false;
        }

        $lastArg = $arguments[count($arguments) - 1];
        if (!$lastArg instanceof VectorNode) {
            return false;
        }

        $meta = $fn->getMeta();
        $isVariadic = $meta->find('is-variadic') ?? $meta->find(Keyword::create('is-variadic'));
        if ($isVariadic !== false) {
            return false;
        }

        $minArity = $meta->find('min-arity') ?? $meta->find(Keyword::create('min-arity'));
        if (!is_int($minArity)) {
            return false;
        }

        $leading = count($arguments) - 1;
        $vecElements = $lastArg->getArgs();
        $expected = $leading + count($vecElements);
        if ($minArity !== $expected) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($fn);
        $this->outputEmitter->emitStr(')->__invoke(', $loc);

        $leadingArgs = array_slice($arguments, 0, $leading);
        $positional = [...$leadingArgs, ...$vecElements];
        $total = count($positional);
        foreach ($positional as $i => $arg) {
            $this->outputEmitter->emitNode($arg);
            if ($i < $total - 1) {
                $this->outputEmitter->emitStr(', ', $loc);
            }
        }

        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }
}
