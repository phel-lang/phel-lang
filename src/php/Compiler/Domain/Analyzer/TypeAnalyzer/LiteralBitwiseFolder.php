<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;

use function array_slice;
use function count;
use function is_int;

/**
 * Compile-time evaluation of the bitwise `php/` interop ops that
 * `bit-and`, `bit-or`, `bit-xor`, `bit-shift-left`, `bit-shift-right`, and
 * `bit-not` inline-expand to (`& | ^ << >> ~`). Folds both user-written
 * `(php/& 12 10)` calls and the `bit-*` core fns whose `:inline` lowers to
 * them.
 *
 * Skips when any arg is non-int (Phel core asserts int-only), when a shift
 * amount is negative (runtime error preserved), and when the op is `~` and
 * the arity is not 1.
 */
final readonly class LiteralBitwiseFolder
{
    /** @var array<string, true> */
    private const array PHP_BITWISE_OPS = [
        '&' => true,
        '|' => true,
        '^' => true,
        '<<' => true,
        '>>' => true,
        '~' => true,
    ];

    public function supports(string $op): bool
    {
        return isset(self::PHP_BITWISE_OPS[$op]);
    }

    /**
     * @param list<AbstractNode> $args
     */
    public function fold(string $op, array $args): ?int
    {
        $ints = $this->extractIntLiterals($args);
        if ($ints === null) {
            return null;
        }

        if ($op === '~') {
            if (count($ints) !== 1) {
                return null;
            }

            return ~$ints[0];
        }

        if (count($ints) < 2) {
            return null;
        }

        if (($op === '<<' || $op === '>>') && $this->anyNegative(array_slice($ints, 1))) {
            return null;
        }

        $acc = $ints[0];
        $rest = array_slice($ints, 1);
        foreach ($rest as $n) {
            $acc = match ($op) {
                '&' => $acc & $n,
                '|' => $acc | $n,
                '^' => $acc ^ $n,
                '<<' => $acc << $n,
                '>>' => $acc >> $n,
                default => null,
            };

            if ($acc === null) {
                return null;
            }
        }

        return $acc;
    }

    /**
     * @param list<AbstractNode> $args
     *
     * @return list<int>|null
     */
    private function extractIntLiterals(array $args): ?array
    {
        $ints = [];
        foreach ($args as $arg) {
            if (!$arg instanceof LiteralNode) {
                return null;
            }

            $value = $arg->getValue();
            if (!is_int($value)) {
                return null;
            }

            $ints[] = $value;
        }

        return $ints;
    }

    /**
     * @param list<int> $values
     */
    private function anyNegative(array $values): bool
    {
        return array_any($values, static fn(int $v): bool => $v < 0);
    }
}
