<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\EqualsInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

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
        $subject = Symbol::gen('__phel_match_');
        $this->outputEmitter->emitContextPrefix($env, $loc);
        $this->emitMatchArms($shape, $subject, false, $loc, $shape['init']);
        $this->outputEmitter->emitContextSuffix($env, $loc);
    }

    /**
     * @param LoweredMatch $shape
     */
    private function emitMatchArms(array $shape, Symbol $subject, bool $valueEquality, ?SourceLocation $loc, ?AbstractNode $init = null): void
    {
        $this->outputEmitter->emitStr('match (', $loc);
        if ($valueEquality) {
            $this->outputEmitter->emitStr('true', $loc);
        } elseif (!$init instanceof AbstractNode) {
            $this->outputEmitter->emitPhpVariable($subject, $loc);
        } else {
            $this->outputEmitter->emitStr('(', $loc);
            $this->outputEmitter->emitPhpVariable($subject, $loc);
            $this->outputEmitter->emitStr(' = ', $loc);
            $this->outputEmitter->emitNode($init);
            $this->outputEmitter->emitStr(')', $loc);
        }

        $this->outputEmitter->emitStr(') { ', $loc);

        foreach ($shape['arms'] as $arm) {
            if ($valueEquality) {
                $this->emitLiteralEquality($subject, $arm['key'], $arm['literalFirst'], $loc);
            } else {
                $this->emitMatchValue($arm['key'], $loc);
            }

            $this->outputEmitter->emitStr(' => ', $loc);
            $this->emitMatchValue($arm['expr'], $loc);
            $this->outputEmitter->emitStr(', ', $loc);
        }

        $this->outputEmitter->emitStr('default => ', $loc);
        if ($valueEquality) {
            $this->emitMatchValue($shape['fallback'], $loc);
        } else {
            // Identity cannot bypass an earlier value-equal key: object keys are final Keyword values.
            $this->outputEmitter->emitStr('(', $loc);
            $this->outputEmitter->emitPhpVariable($subject, $loc);
            $this->outputEmitter->emitStr(' instanceof \\' . EqualsInterface::class . ' ? ', $loc);
            $this->emitMatchArms($shape, $shape['comparisonLocal']?->getName() ?? $subject, true, $loc);
            $this->outputEmitter->emitStr(' : ', $loc);
            $this->emitMatchValue($shape['fallback'], $loc);
            $this->outputEmitter->emitStr(')', $loc);
        }

        $this->outputEmitter->emitStr(' }', $loc);
    }

    private function emitLiteralEquality(Symbol $subject, mixed $key, bool $literalFirst, ?SourceLocation $loc): void
    {
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitPhpVariable($subject, $loc);
        $this->outputEmitter->emitStr(' === ', $loc);
        $this->emitMatchValue($key, $loc);
        $this->outputEmitter->emitStr(' || (', $loc);
        if ($literalFirst && $key instanceof Keyword) {
            $this->emitMatchValue($key, $loc);
            $this->outputEmitter->emitStr('->equals(', $loc);
            $this->outputEmitter->emitPhpVariable($subject, $loc);
        } else {
            $this->outputEmitter->emitPhpVariable($subject, $loc);
            $this->outputEmitter->emitStr(' instanceof \\' . EqualsInterface::class . ' && ', $loc);
            $this->outputEmitter->emitPhpVariable($subject, $loc);
            $this->outputEmitter->emitStr('->equals(', $loc);
            $this->emitMatchValue($key, $loc);
        }

        $this->outputEmitter->emitStr(')))', $loc);
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
