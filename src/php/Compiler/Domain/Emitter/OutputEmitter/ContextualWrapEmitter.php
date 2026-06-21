<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\SourceLocation;

/**
 * Shared "context-aware IIFE-or-statements" kernel for the interop emitters
 * (`php/->`, `php/oset`, `php/new`) whose computed target needs a temp var to
 * guarantee exactly-once evaluation.
 *
 * In expression context the setup + result must live inside an IIFE so the temp
 * can host a `return`; the by-reference local capture (`ByRefLocalCollector`)
 * is computed lazily here, only on this path. In statement and return context
 * the IIFE is elided: the setup runs as plain statements and the result is
 * bracketed with the usual context prefix/suffix.
 */
final readonly class ContextualWrapEmitter
{
    public function __construct(private OutputEmitterInterface $outputEmitter) {}

    /**
     * @param callable():void $emitSetup      emits the temp assignment(s) and any
     *                                        guards as plain statements; runs in
     *                                        every context, exactly once
     * @param callable():void $emitResultExpr emits the bare value-producing
     *                                        expression (no `return`, no `;`)
     */
    public function emit(
        AbstractNode $node,
        callable $emitSetup,
        callable $emitResultExpr,
    ): void {
        $env = $node->getEnv();
        $loc = $node->getStartSourceLocation();

        if ($env->isContext(NodeEnvironment::CONTEXT_EXPRESSION)) {
            $this->emitWrapped($env, $loc, $node, $emitSetup, $emitResultExpr);

            return;
        }

        $emitSetup();
        $this->outputEmitter->emitContextPrefix($env, $loc);
        $emitResultExpr();
        $this->outputEmitter->emitContextSuffix($env, $loc);
    }

    /**
     * @param callable():void $emitSetup
     * @param callable():void $emitResultExpr
     */
    private function emitWrapped(
        NodeEnvironmentInterface $env,
        ?SourceLocation $loc,
        AbstractNode $node,
        callable $emitSetup,
        callable $emitResultExpr,
    ): void {
        $this->outputEmitter->emitFnWrapPrefix(
            $env,
            $loc,
            new ByRefLocalCollector()->collect($node),
        );

        $emitSetup();

        $this->outputEmitter->emitStr('return ', $loc);
        $emitResultExpr();
        $this->outputEmitter->emitStr(';', $loc);

        $this->outputEmitter->emitFnWrapSuffix($loc);
    }
}
