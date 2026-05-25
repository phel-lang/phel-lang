<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\TryNode;

use function array_pop;
use function is_array;

/**
 * Recursive subtree scan that returns `true` as soon as it encounters
 * a node whose emitter wraps the result in a PHP closure with a
 * captured `use(...)` clause. {@see LetSimplifier} bails out for such
 * subtrees because the capture list is fixed at analysis time and
 * would dangle if the binding it names were dropped.
 *
 * Currently flagged: {@see FnNode}, {@see MultiFnNode}, {@see ForeachNode},
 * {@see TryNode} (every one of them emits a `use(...)` clause in
 * expression context).
 *
 * The walker introspects each `AbstractNode` via `(array) $obj`, which
 * exposes every property value regardless of visibility, so it works
 * without enumerating every node subclass.
 */
final class LocalVarFnGuard
{
    public static function containsClosure(AbstractNode $node): bool
    {
        if (self::emitsClosure($node)) {
            return true;
        }

        $stack = [$node];
        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ((array) $current as $value) {
                if ($value instanceof AbstractNode) {
                    if (self::emitsClosure($value)) {
                        return true;
                    }

                    $stack[] = $value;
                    continue;
                }

                if (is_array($value)) {
                    foreach ($value as $item) {
                        if (!$item instanceof AbstractNode) {
                            continue;
                        }

                        if (self::emitsClosure($item)) {
                            return true;
                        }

                        $stack[] = $item;
                    }
                }
            }
        }

        return false;
    }

    private static function emitsClosure(AbstractNode $node): bool
    {
        return $node instanceof FnNode
            || $node instanceof MultiFnNode
            || $node instanceof ForeachNode
            || $node instanceof TryNode;
    }
}
