<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;

use function array_all;

/**
 * Whether an analysed node renders as a single PHP expression — no statement
 * wrapping, no IIFE. Both the `if` ternary lowering ({@see IfEmitter}) and the
 * `and`/`or` short-circuit lowering ({@see AndOrShortCircuitLowerer}) need this
 * test before splicing a node into an expression position; this is their
 * shared definition.
 */
final readonly class SimpleExpressionNode
{
    private function __construct() {}

    /**
     * @param bool $unwrapTransparentDo when true a `(do …)` whose body is a
     *                                  single return expression is treated as
     *                                  transparent (the `if` ternary lowerer
     *                                  relies on this); when false a `do` is
     *                                  never simple, preserving the and/or
     *                                  lowerer's IIFE path
     */
    public static function is(AbstractNode $node, bool $unwrapTransparentDo): bool
    {
        if ($unwrapTransparentDo && $node instanceof DoNode) {
            return $node->getStmts() === [] && self::is($node->getRet(), true);
        }

        if ($node instanceof LocalVarNode
            || $node instanceof LiteralNode
            || $node instanceof GlobalVarNode
            || $node instanceof PhpVarNode
        ) {
            return true;
        }

        if ($node instanceof CallNode) {
            return array_all([$node->getFn(), ...$node->getArguments()], static fn(AbstractNode $child): bool => self::is($child, $unwrapTransparentDo));
        }

        return false;
    }
}
