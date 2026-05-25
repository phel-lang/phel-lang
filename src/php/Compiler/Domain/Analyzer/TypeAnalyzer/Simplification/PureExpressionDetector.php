<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\ConstantFolder;

/**
 * Conservative purity oracle used by the simplification pass.
 *
 * "Pure" here means: evaluating this node has no observable effect
 * (no I/O, no mutation, no exception under any input the analyser
 * could not statically reject). When in doubt, return `false` — a
 * false negative (keeping a dead expression) is cheap; a false
 * positive (dropping a side effect) is silently wrong.
 *
 * For `CallNode` purity we delegate to {@see ConstantFolder}: only
 * calls the folder can statically evaluate are considered pure. This
 * automatically excludes calls that *could* throw at runtime
 * (`(abs nil)`, `(quot 1 0)`, `(nth [] 99)`, …) because the folder
 * refuses to lift those exceptions to compile time.
 */
final readonly class PureExpressionDetector
{
    public function __construct(
        private ConstantFolder $folder = new ConstantFolder(),
    ) {}

    public function isPure(AbstractNode $node): bool
    {
        if ($node instanceof LiteralNode || $node instanceof LocalVarNode || $node instanceof GlobalVarNode) {
            return true;
        }

        if ($node instanceof CallNode) {
            // A call is pure iff the folder can compute its exact value.
            // That guarantees the call has no observable effect and no
            // runtime exception under its current arguments.
            return $this->folder->fold($node) instanceof AbstractNode;
        }

        return false;
    }
}
