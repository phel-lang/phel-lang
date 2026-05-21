<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

use function in_array;

/**
 * Syntactic predicate: can the emitter prove that a node produces a value
 * already in a shape `foreach` (or PHP argument unpacking) accepts? Used
 * by `ForeachEmitter` and `ApplyEmitter` to drop the `Seq::toIterable` /
 * `Seq::toApplyArguments` adapter wrappers when they would be no-ops.
 *
 * Beyond the obvious literal-shape matches the predicate also consumes
 * the analyser's inferred type for `LocalVarNode` (via
 * {@see LocalVarNode::getInferredType()}), so `(defn f [^array xs] …)` or
 * `(let [^"\Iterator" it expr] …)` style annotations unlock the same
 * fast paths as a literal would.
 */
final readonly class IterableTarget
{
    /**
     * Iterable type tags the emitter trusts not to need
     * `Seq::toIterable` coercion. `iterable` and `array` are PHP-native;
     * the Phel persistent-collection interfaces all implement
     * `IteratorAggregate`, which `foreach` accepts.
     */
    private const array ITERABLE_TAGS = [
        'array',
        'iterable',
        'Iterator',
        'Traversable',
        'IteratorAggregate',
        PersistentVectorInterface::class,
        PersistentMapInterface::class,
        PersistentHashSetInterface::class,
        PersistentListInterface::class,
    ];

    private function __construct() {}

    /**
     * A node whose value always implements `IteratorAggregate` or is a
     * PHP array. Phel collection literals (vector / map / set) emit
     * `\Phel::vector([...])`, `\Phel::map(...)`, `\Phel::set(...)`, each
     * implementing PHP's iterable protocol. `(php/array …)` emits a PHP
     * array directly. Both are safe for `foreach (… as $v)`. A
     * `LocalVarNode` whose binding carries an iterable `:tag` is also
     * accepted via the analyser's inferred type.
     */
    public static function isIterable(AbstractNode $node): bool
    {
        if ($node instanceof VectorNode
            || $node instanceof MapNode
            || $node instanceof SetNode
        ) {
            return true;
        }

        if (self::isPhpArray($node)) {
            return true;
        }

        return $node instanceof LocalVarNode
            && self::isIterableTag($node->getInferredType());
    }

    /**
     * A node whose emitted PHP expression evaluates to a native PHP array
     * (not just an iterable). `(php/array a b c)` and any local whose
     * binding carries the `array` tag both round-trip as a PHP `array`,
     * so PHP's `...$arr` spread accepts them without conversion.
     */
    public static function isPhpArray(AbstractNode $node): bool
    {
        if ($node instanceof CallNode) {
            $fn = $node->getFn();
            return $fn instanceof PhpVarNode && $fn->getName() === 'array';
        }

        return $node instanceof LocalVarNode
            && $node->getInferredType() === 'array';
    }

    private static function isIterableTag(?string $tag): bool
    {
        if ($tag === null) {
            return false;
        }

        $normalised = ltrim($tag, '\\');
        return in_array($normalised, self::ITERABLE_TAGS, true);
    }
}
