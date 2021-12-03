<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\FnNode;
use Phel\Lang\Keyword;

final class MethodEmitter
{
    use WithOutputEmitterTrait;

    public function emit(string $methodName, FnNode $node): void
    {
        $this->emitInvokeFunctionBegin($methodName, $node);
        $this->emitInvokeFunctionParameters($node);
        $this->emitInvokeFunctionBody($node);
        $this->emitInvokeFunctionEnd($node);
    }

    private function emitInvokeFunctionBegin(string $methodName, FnNode $node): void
    {
        $this->outputEmitter->emitStr('public function ' . $methodName . '(', $node->getStartSourceLocation());
    }

    private function emitInvokeFunctionParameters(FnNode $node): void
    {
        $paramsCount = count($node->getParams());

        foreach ($node->getParams() as $i => $p) {
            if ($i === $paramsCount - 1 && $node->isVariadic()) {
                $this->outputEmitter->emitPhpVariable($p, null, false, true);
            } else {
                $meta = $p->getMeta();
                $isReference = $meta && $meta->find(Keyword::create('reference')) === true;
                $this->outputEmitter->emitPhpVariable($p, null, $isReference);
            }

            if ($i < $paramsCount - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        // Use Parameter extraction
        foreach ($node->getUses() as $i => $u) {
            $shadowed = $node->getEnv()->getShadowed($u);
            if ($shadowed) {
                $u = $shadowed;
            }

            $varName = $this->outputEmitter->mungeEncode($u->getName());

            $this->outputEmitter->emitLine(
                '$' . $varName . ' = $this->' . $varName . ';',
                $node->getStartSourceLocation()
            );
        }

        // Variadic Parameter
        if ($node->isVariadic()) {
            $p = $node->getParams()[count($node->getParams()) - 1];

            $this->outputEmitter->emitLine(
                '$' . $this->outputEmitter->mungeEncode($p->getName())
                . ' = new \Phel\Lang\PhelArray($' . $this->outputEmitter->mungeEncode($p->getName()) . ');',
                $node->getStartSourceLocation()
            );
        }
    }

    private function emitInvokeFunctionBody(FnNode $node): void
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

    private function emitInvokeFunctionEnd(FnNode $node): void
    {
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }
}
