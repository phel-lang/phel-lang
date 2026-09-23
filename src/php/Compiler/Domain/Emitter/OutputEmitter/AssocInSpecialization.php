<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;

use function count;

/**
 * Call-site eligibility for `(assoc-in ds [k1 k2 …] v)` and
 * `(update-in ds [k1 k2 …] f & args)` where the path is a syntactic literal
 * `VectorNode`, which {@see NodeEmitter\CallEmitter} lowers to one
 * {@see \Phel\Lang\AssocIn} call with the keys as a PHP array (#3328).
 *
 * @internal
 */
final readonly class AssocInSpecialization
{
    private function __construct() {}

    public static function isLiteralPathAssocIn(CallNode $node): bool
    {
        return self::literalPathKeys($node) !== null;
    }

    /**
     * Returns the literal path key nodes for an `assoc-in` with three
     * arguments or an `update-in` with three or more, or `null` when the
     * call keeps the runtime fn.
     *
     * An empty path qualifies: `AssocIn` writes under a nil key for it, as
     * the runtime's `[k & ks]` destructuring does. A path carrying metadata
     * does not, since its meta form is evaluated with the vector and the
     * lowering never builds one.
     *
     * @return list<AbstractNode>|null
     */
    public static function literalPathKeys(CallNode $node): ?array
    {
        $argc = count($node->getArguments());
        $eligible = match (PhelCoreCall::nameOf($node)) {
            'assoc-in' => $argc === 3,
            'update-in' => $argc >= 3,
            default => false,
        };
        if (!$eligible) {
            return null;
        }

        $path = $node->getArguments()[1];
        if (!$path instanceof VectorNode || $path->getMeta() instanceof MapNode) {
            return null;
        }

        return $path->getArgs();
    }
}
