<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

use function count;

final readonly class MethodEmitter
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private ClosureEmitterHelper $closureHelper,
    ) {}

    public function emit(string $methodName, FnNode $node): void
    {
        $this->emitMethodBegin($methodName, $node);
        $this->emitMethodParameterList($node);
        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->emitMethodParametersExtraction($node);
        $this->emitSelfNameBinding($node);
        $this->emitMethodVariadicParameters($node);
        $this->emitMethodBody($node);
        $this->emitMethodEnd($node);
    }

    /**
     * Emits just the parameter list (without surrounding parens or braces).
     * Used by FnAsClassEmitter for both class and closure emission paths.
     */
    public function emitParameters(FnNode $node): void
    {
        $paramsCount = count($node->getParams());

        foreach ($node->getParams() as $i => $symbol) {
            if ($i === $paramsCount - 1 && $node->isVariadic()) {
                $this->outputEmitter->emitPhpVariable($symbol, $loc = null, $asReference = false, $isVariadic = true);
            } else {
                $meta = $symbol->getMeta();
                $isReference = $meta instanceof PersistentMapInterface && $meta->find(Keyword::create('reference')) === true;
                $this->outputEmitter->emitPhpVariable($symbol, $loc = null, $isReference);
            }

            if ($i < $paramsCount - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }
    }

    /**
     * Emits the function body: variadic wrapping, recur loop, and the body node.
     * Used by FnAsClassEmitter for the closure emission path.
     */
    public function emitBody(FnNode $node): void
    {
        if ($node->isMultiArityChild()) {
            $this->emitSelfNameBinding($node);
        }

        $this->emitMethodVariadicParameters($node);
        $this->emitMethodBody($node);
    }

    /**
     * For named fns compiled as invokable classes, bind the fn's own name to
     * `$this` at the top of the method body so self-recursive references
     * resolve to the class instance (which is callable via __invoke).
     */
    private function emitSelfNameBinding(FnNode $node): void
    {
        $name = $node->getName();
        if (!$name instanceof Symbol) {
            return;
        }

        $varName = $this->outputEmitter->mungeEncode($name->getName());

        $this->outputEmitter->emitLine(
            '$' . $varName . ' = $this;',
            $node->getStartSourceLocation(),
        );
    }

    private function emitMethodBegin(string $methodName, FnNode $node): void
    {
        $this->outputEmitter->emitStr('public function ' . $this->outputEmitter->mungeEncode($methodName) . '(', $node->getStartSourceLocation());
    }

    private function emitMethodParameterList(FnNode $node): void
    {
        $this->emitParameters($node);
    }

    private function emitMethodParametersExtraction(FnNode $node): void
    {
        foreach ($node->getUses() as $use) {
            $varName = $this->munge($this->closureHelper->normalizeUse($use, $node->getEnv()));

            $this->outputEmitter->emitLine(
                '$' . $varName . ' = $this->' . $varName . ';',
                $node->getStartSourceLocation(),
            );
        }
    }

    private function emitMethodVariadicParameters(FnNode $node): void
    {
        if ($node->isVariadic()) {
            $p = $node->getParams()[count($node->getParams()) - 1];
            $varName = $this->munge($p);

            $this->outputEmitter->emitLine(
                '$' . $varName . ' = \Phel::vector($' . $varName . ');',
                $node->getStartSourceLocation(),
            );
        }
    }

    private function munge(Symbol $symbol): string
    {
        return $this->outputEmitter->mungeEncode($symbol->getName());
    }

    private function emitMethodBody(FnNode $node): void
    {
        if ($node->getRecurs()) {
            $this->outputEmitter->emitLine('while (true) {', $node->getStartSourceLocation());
            $this->outputEmitter->increaseIndentLevel();
        }

        $this->outputEmitter->emitNode($node->getBody());

        if ($node->getRecurs()) {
            $this->outputEmitter->emitLine('break;', $node->getStartSourceLocation());
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());
        }
    }

    private function emitMethodEnd(FnNode $node): void
    {
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }
}
