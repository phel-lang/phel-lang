<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;

/**
 * Syntactic predicate: can the emitter prove that a node produces a value
 * already in a shape `foreach` (or PHP argument unpacking) accepts? Used
 * by `ForeachEmitter` and `ApplyEmitter` to drop the `Seq::toIterable` /
 * `Seq::toApplyArguments` adapter wrappers when they would be no-ops.
 */
final readonly class IterableTarget
{
    private function __construct() {}

    /**
     * A node whose value always implements `IteratorAggregate` or is a
     * PHP array. Phel collection literals (vector / map / set) emit
     * `\Phel::vector([...])`, `\Phel::map(...)`, `\Phel::set(...)`, each
     * implementing PHP's iterable protocol. `(php/array …)` emits a PHP
     * array directly. Both are safe for `foreach (… as $v)`.
     */
    public static function isIterable(AbstractNode $node): bool
    {
        return $node instanceof VectorNode
            || $node instanceof MapNode
            || $node instanceof SetNode
            || self::isPhpArray($node);
    }

    /**
     * A node whose emitted PHP expression evaluates to a native PHP array
     * (not just an iterable). `(php/array a b c)` is currently the only
     * source. Native arrays unpack into positional args without needing
     * `Seq::toApplyArguments` to walk them.
     */
    public static function isPhpArray(AbstractNode $node): bool
    {
        if (!$node instanceof CallNode) {
            return false;
        }

        $fn = $node->getFn();
        return $fn instanceof PhpVarNode && $fn->getName() === 'array';
    }
}
