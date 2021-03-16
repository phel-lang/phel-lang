<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\FnNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Keyword;

final class FnAsClassEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof FnNode);

        $this->emitClassBegin($node);
        $this->emitProperties($node);
        $this->emitConstructor($node);
        $this->emitInvokeFunctionBegin($node);
        $this->emitInvokeFunctionParameters($node);
        $this->emitInvokeFunctionBody($node);
        $this->emitInvokeFunctionEnd($node);
        $this->emitClassEnd($node);
    }

    private function emitClassBegin(FnNode $node): void
    {
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('new class(', $node->getStartSourceLocation());

        $usesCount = count($node->getUses());
        foreach ($node->getUses() as $i => $u) {
            $loc = $u->getStartLocation();
            $shadowed = $node->getEnv()->getShadowed($u);
            if ($shadowed) {
                $u = $shadowed;
            }

            $this->outputEmitter->emitPhpVariable($u, $loc);

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

        foreach ($node->getUses() as $i => $u) {
            $shadowed = $node->getEnv()->getShadowed($u);
            if ($shadowed) {
                $u = $shadowed;
            }

            $this->outputEmitter->emitLine(
                'private $' . $this->outputEmitter->mungeEncode($u->getName()) . ';',
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
            foreach ($node->getUses() as $i => $u) {
                $shadowed = $node->getEnv()->getShadowed($u);
                if ($shadowed) {
                    $u = $shadowed;
                }

                $this->outputEmitter->emitPhpVariable($u, $node->getStartSourceLocation());

                if ($i < $usesCount - 1) {
                    $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
                }
            }

            $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
            $this->outputEmitter->increaseIndentLevel();

            // Constructor assignment
            foreach ($node->getUses() as $i => $u) {
                $shadowed = $node->getEnv()->getShadowed($u);
                if ($shadowed) {
                    $u = $shadowed;
                }

                $varName = $this->outputEmitter->mungeEncode($u->getName());
                $this->outputEmitter->emitLine(
                    '$this->' . $varName . ' = $' . $varName . ';',
                    $node->getStartSourceLocation()
                );
            }

            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
        }
    }

    private function emitInvokeFunctionBegin(FnNode $node): void
    {
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('public function __invoke(', $node->getStartSourceLocation());
    }

    private function emitInvokeFunctionParameters(FnNode $node): void
    {
        $paramsCount = count($node->getParams());

        foreach ($node->getParams() as $i => $p) {
            if ($i === $paramsCount - 1 && $node->isVariadic()) {
                $this->outputEmitter->emitPhpVariable($p, null, false, true);
            } else {
                $meta = $p->getMeta();
                $this->outputEmitter->emitPhpVariable($p, null, $meta->find(new Keyword('reference')) === true);
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

    private function emitClassEnd(FnNode $node): void
    {
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
