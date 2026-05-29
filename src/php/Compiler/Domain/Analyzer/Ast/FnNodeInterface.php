<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

/**
 * Shared contract for the function-definition nodes a `def` may store in
 * the inliner side-table: a single-arity {@see FnNode} or a multi-arity
 * {@see MultiFnNode}. Lets the side-table and the call inliner stay
 * single-typed instead of threading a `FnNode|MultiFnNode` union.
 */
interface FnNodeInterface
{
    /**
     * Returns the fixed (non-variadic) arity whose parameter count equals
     * $argCount, or null when no matching arity exists.
     */
    public function arityFor(int $argCount): ?FnNode;
}
