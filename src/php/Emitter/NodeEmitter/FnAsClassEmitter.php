<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\FnNode;
use Phel\Ast\Node;
use Phel\Emitter;
use Phel\Emitter\NodeEmitter;
use Phel\Lang\Keyword;
use Phel\Munge;

final class FnAsClassEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof FnNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitter->emitStr('new class(', $node->getStartSourceLocation());

        $usesCount = count($node->getUses());
        foreach ($node->getUses() as $i => $u) {
            $loc = $u->getStartLocation();
            $shadowed = $node->getEnv()->getShadowed($u);
            if ($shadowed) {
                $u = $shadowed;
            }

            $this->emitter->emitPhpVariable($u, $loc);

            if ($i < $usesCount - 1) {
                $this->emitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->emitter->emitLine(') extends \Phel\Lang\AFn {', $node->getStartSourceLocation());
        $this->emitter->indentLevel++;

        $this->emitter->emitLine(
            'public const BOUND_TO = "' . addslashes(Munge::encodeNs($node->getEnv()->getBoundTo())) . '";',
            $node->getStartSourceLocation()
        );

        foreach ($node->getUses() as $i => $u) {
            $shadowed = $node->getEnv()->getShadowed($u);
            if ($shadowed) {
                $u = $shadowed;
            }

            $this->emitter->emitLine(
                'private $' . $this->emitter->munge($u->getName()) . ';',
                $node->getStartSourceLocation()
            );
        }

        // Constructor
        if ($usesCount) {
            $this->emitter->emitLine();
            $this->emitter->emitStr('public function __construct(', $node->getStartSourceLocation());

            // Constructor parameter
            foreach ($node->getUses() as $i => $u) {
                $shadowed = $node->getEnv()->getShadowed($u);
                if ($shadowed) {
                    $u = $shadowed;
                }

                $this->emitter->emitPhpVariable($u, $node->getStartSourceLocation());

                if ($i < $usesCount - 1) {
                    $this->emitter->emitStr(', ', $node->getStartSourceLocation());
                }
            }

            $this->emitter->emitLine(') {', $node->getStartSourceLocation());
            $this->emitter->indentLevel++;

            // Constructor assignment
            foreach ($node->getUses() as $i => $u) {
                $shadowed = $node->getEnv()->getShadowed($u);
                if ($shadowed) {
                    $u = $shadowed;
                }

                $varName = $this->emitter->munge($u->getName());
                $this->emitter->emitLine(
                    '$this->' . $varName . ' = $' . $varName . ';',
                    $node->getStartSourceLocation()
                );
            }

            $this->emitter->indentLevel--;
            $this->emitter->emitLine('}', $node->getStartSourceLocation());
        }

        // __invoke Function
        $this->emitter->emitLine();
        $this->emitter->emitStr('public function __invoke(', $node->getStartSourceLocation());

        // Function Parameters
        $paramsCount = count($node->getParams());
        foreach ($node->getParams() as $i => $p) {
            if ($i === $paramsCount - 1 && $node->isVariadic()) {
                $this->emitter->emitPhpVariable($p, null, false, true);
            } else {
                $meta = $p->getMeta();
                $this->emitter->emitPhpVariable($p, null, $meta[new Keyword('reference')] ?? false);
            }

            if ($i < $paramsCount - 1) {
                $this->emitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->emitter->emitLine(') {', $node->getStartSourceLocation());
        $this->emitter->indentLevel++;

        // Use Parameter extraction
        foreach ($node->getUses() as $i => $u) {
            $shadowed = $node->getEnv()->getShadowed($u);
            if ($shadowed) {
                $u = $shadowed;
            }

            $varName = $this->emitter->munge($u->getName());
            $this->emitter->emitLine('$' . $varName . ' = $this->' . $varName . ';', $node->getStartSourceLocation());
        }

        // Variadic Parameter
        if ($node->isVariadic()) {
            $p = $node->getParams()[count($node->getParams()) - 1];
            $this->emitter->emitLine(
                '$' . $this->emitter->munge($p->getName())
                . ' = new \Phel\Lang\PhelArray($' . $this->emitter->munge($p->getName()) . ');',
                $node->getStartSourceLocation()
            );
        }

        // Body
        if ($node->getRecurs()) {
            $this->emitter->emitLine('while (true) {', $node->getStartSourceLocation());
            $this->emitter->indentLevel++;
        }
        $this->emitter->emitNode($node->getBody());
        if ($node->getRecurs()) {
            $this->emitter->emitLine('break;', $node->getStartSourceLocation());
            $this->emitter->indentLevel--;
            $this->emitter->emitStr('}', $node->getStartSourceLocation());
        }

        // End of __invoke
        $this->emitter->indentLevel--;
        $this->emitter->emitLine();
        $this->emitter->emitLine('}', $node->getStartSourceLocation());

        // End of class
        $this->emitter->indentLevel--;
        $this->emitter->emitStr('}', $node->getStartSourceLocation());

        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
