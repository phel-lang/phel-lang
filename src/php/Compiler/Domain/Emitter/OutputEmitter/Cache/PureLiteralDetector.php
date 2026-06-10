<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\Cache;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;

/**
 * A pure literal is a leaf scalar/quote, or a collection literal whose
 * children are all pure. Pure literals produce the same value on every
 * evaluation, so the emitter can safely hoist them to a per-fn static
 * cache and reuse the persistent collection across calls.
 */
final class PureLiteralDetector
{
    public static function isPure(AbstractNode $node): bool
    {
        return match (true) {
            $node instanceof LiteralNode, $node instanceof QuoteNode => true,
            $node instanceof VectorNode => self::allPure($node->getArgs()),
            $node instanceof MapNode => self::allPure($node->getKeyValues()),
            $node instanceof SetNode => self::allPure($node->getValues()),
            default => false,
        };
    }

    /**
     * @param array<int, AbstractNode> $nodes
     */
    private static function allPure(array $nodes): bool
    {
        return array_all($nodes, static fn(AbstractNode $node): bool => self::isPure($node));
    }
}
