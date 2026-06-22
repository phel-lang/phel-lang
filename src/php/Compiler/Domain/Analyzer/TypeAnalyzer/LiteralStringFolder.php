<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;

use function is_string;

/**
 * Compile-time evaluation of `phel.core` equality fns (`= not=`) over string
 * literals.
 *
 * Phel `=` on two PHP strings routes through `equals1`, which falls back to
 * `php/=== a b` (identity on the string values) once neither operand
 * implements `EqualsInterface`. PHP `===` over strings is value equality, so
 * folding `(= "a" "a")` → `true` is byte-faithful to the runtime result.
 *
 * Scope is intentionally narrow: every argument must be a {@see LiteralNode}
 * holding a primitive `string`. Mixed-type calls such as `(= "1" 1)` stay
 * un-folded and route through the runtime equality dispatcher, so this folder
 * never has to reproduce cross-type `=` semantics.
 */
final readonly class LiteralStringFolder
{
    use PairwiseLiteralFoldingTrait;

    /** @var array<string, true> */
    private const array SUPPORTED = ['=' => true, 'not=' => true];

    public function supports(string $fnName): bool
    {
        return isset(self::SUPPORTED[$fnName]);
    }

    /**
     * Extracts string literals from the call args and evaluates `$fnName` over
     * them. Returns `null` when any arg is not a string literal or the arity is
     * not foldable.
     *
     * @param list<AbstractNode> $args
     */
    public function fold(string $fnName, array $args): ?bool
    {
        $strings = $this->extractStringLiterals($args);
        if ($strings === null) {
            return null;
        }

        return match ($fnName) {
            '=' => $this->foldPairwise($strings, static fn($a, $b): bool => $a === $b),
            'not=' => $this->negate($this->foldPairwise($strings, static fn($a, $b): bool => $a === $b)),
            default => null,
        };
    }

    /**
     * @param list<AbstractNode> $args
     *
     * @return list<string>|null
     */
    private function extractStringLiterals(array $args): ?array
    {
        $strings = [];
        foreach ($args as $arg) {
            if (!$arg instanceof LiteralNode) {
                return null;
            }

            $value = $arg->getValue();
            if (!is_string($value)) {
                return null;
            }

            $strings[] = $value;
        }

        return $strings;
    }
}
