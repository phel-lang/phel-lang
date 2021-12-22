<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\FnNode;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

final class MethodEmitter
{
    use WithOutputEmitterTrait;

    public function emit(string $methodName, FnNode $node): void
    {
        $this->emitMethodBegin($methodName, $node);
        $this->emitMethodParameters($node);
        $this->emitMethodBody($node);
        $this->emitMethodEnd($node);
    }

    private function emitMethodBegin(string $methodName, FnNode $node): void
    {
        $this->outputEmitter->emitStr('public function ' . $this->outputEmitter->mungeEncode($methodName) . '(', $node->getStartSourceLocation());
    }

    private function emitMethodParameters(FnNode $node): void
    {
        $paramsCount = count($node->getParams());

        foreach ($node->getParams() as $i => $p) {
            if ($i === $paramsCount - 1 && $node->isVariadic()) {
                $this->outputEmitter->emitPhpVariable($p, $loc = null, $asReference = false, $isVariadic = true);
            } else {
                $meta = $p->getMeta();
                $isReference = $meta && $meta->find(Keyword::create('reference')) === true;
                $this->outputEmitter->emitPhpVariable($p, $loc = null, $isReference);
            }

            if ($i < $paramsCount - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        $this->emitMethodParametersExtraction($node);
        $this->emitMethodVariadicParameters($node);
    }

    private function emitMethodParametersExtraction(FnNode $node): void
    {
        foreach ($node->getUses() as $use) {
            $normalizedUse = $node->getEnv()->getShadowed($use) ?: $use;
            $varName = $this->munge($normalizedUse);

            $this->outputEmitter->emitLine(
                '$' . $varName . ' = $this->' . $varName . ';',
                $node->getStartSourceLocation()
            );
        }
    }

    private function emitMethodVariadicParameters(FnNode $node): void
    {
        if ($node->isVariadic()) {
            $p = $node->getParams()[count($node->getParams()) - 1];
            $varName = $this->munge($p);

            $this->outputEmitter->emitLine(
                '$' . $varName . ' = \Phel\Lang\TypeFactory::getInstance()->persistentVectorFromArray($' . $varName . ');',
                $node->getStartSourceLocation()
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
