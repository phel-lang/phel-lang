<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;

/**
 * Emits the PHP `match` expression for a lowered `cond` / `case` chain
 * shape produced by {@see IfChainMatchLowerer}. Composing emitters must
 * also use {@see WithOutputEmitterTrait}.
 *
 * @phpstan-import-type LoweredMatch from IfChainMatchLowerer
 *
 * @internal
 */
trait LoweredMatchEmitterTrait
{
    /**
     * @param LoweredMatch $shape
     */
    private function emitLoweredMatch(array $shape, NodeEnvironmentInterface $env, ?SourceLocation $loc): void
    {
        $this->outputEmitter->emitContextPrefix($env, $loc);
        $this->outputEmitter->emitStr('match (', $loc);
        $this->outputEmitter->emitNode($shape['init']);
        $this->outputEmitter->emitStr(') { ', $loc);

        foreach ($shape['arms'] as $arm) {
            $this->emitMatchValue($arm['key'], $loc);
            $this->outputEmitter->emitStr(' => ', $loc);
            $this->emitMatchValue($arm['expr'], $loc);
            $this->outputEmitter->emitStr(', ', $loc);
        }

        $this->outputEmitter->emitStr('default => ', $loc);
        $this->emitMatchValue($shape['fallback'], $loc);
        $this->outputEmitter->emitStr(' }', $loc);
        $this->outputEmitter->emitContextSuffix($env, $loc);
    }

    /**
     * A keyword arm value goes through the slot `BodyConstantScanner`
     * reserved for it, so a dispatch does not re-intern every key it
     * passes (a `match` evaluates its arm keys in order).
     */
    private function emitMatchValue(mixed $value, ?SourceLocation $loc): void
    {
        $slot = $value instanceof Keyword
            ? $this->outputEmitter->currentConstantScope()?->lookupKeyword($value)
            : null;
        if ($slot === null) {
            $this->outputEmitter->emitLiteral($value);
            return;
        }

        $this->outputEmitter->emitStr('($__phel_const_' . $slot . ' ??= ', $loc);
        $this->outputEmitter->emitLiteral($value);
        $this->outputEmitter->emitStr(')', $loc);
    }
}
