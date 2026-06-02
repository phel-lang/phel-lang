<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Shared\CompilerConstants;

use function assert;
use function count;
use function implode;
use function intdiv;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Compile-time folding of `phel.core` collection/aggregate fns over literal
 * collection nodes: `count` / `first` / `last` / `nth` / `reduce` / `str`.
 *
 * Accessors only fold when every relevant element is itself a `LiteralNode`
 * (so substituting the target element cannot drop a side-effect), and
 * out-of-bounds `nth` keeps the call so Phel's runtime exception is not
 * lifted to compile time. `reduce` is driven step-by-step through
 * {@see LiteralArithmeticFolder} so a step the arithmetic folder refuses
 * (e.g. divide-by-zero) blocks the whole reduction.
 */
final readonly class LiteralCollectionFolder
{
    /**
     * Whitelist of binary `phel.core` fns that can act as a `reduce`
     * reducer at compile time. Limited to numeric reducers whose two-arg
     * variants do not produce a boolean (so `=` / `<` / `<=` / `>` / `>=`
     * are excluded — those are pairwise predicates, not accumulating
     * reducers).
     *
     * @var array<string, true>
     */
    private const array REDUCE_BINARY_OPS = [
        '+' => true,
        '*' => true,
        '-' => true,
        'min' => true,
        'max' => true,
        'mod' => true,
        'quot' => true,
        'rem' => true,
    ];

    public function __construct(
        private LiteralArithmeticFolder $arithmeticFolder = new LiteralArithmeticFolder(),
    ) {}

    /**
     * Folds `count` / `first` / `last` / `nth` against literal collection
     * nodes. Returns `null` when `$fnName` is not a supported accessor.
     */
    public function foldAccessor(string $fnName, CallNode $node): ?AbstractNode
    {
        $args = $node->getArguments();

        if ($fnName === 'count') {
            return $this->foldCount($args, $node);
        }

        if ($fnName === 'first' || $fnName === 'last') {
            return $this->foldVectorPick($fnName, $args, $node);
        }

        if ($fnName === 'nth') {
            return $this->foldNth($args, $node);
        }

        return null;
    }

    /**
     * `(reduce f init coll)` with `f` a whitelisted pure binary
     * `phel.core` op, `init` a numeric literal, and `coll` a vector
     * literal whose elements are all numeric literals.
     */
    public function foldReduce(CallNode $node): ?LiteralNode
    {
        $args = $node->getArguments();
        if (count($args) !== 3) {
            return null;
        }

        [$fnArg, $init, $coll] = $args;

        if (!$fnArg instanceof GlobalVarNode) {
            return null;
        }

        if ($fnArg->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return null;
        }

        $reducerName = $fnArg->getName()->getName();
        if (!isset(self::REDUCE_BINARY_OPS[$reducerName])) {
            return null;
        }

        if (!$init instanceof LiteralNode) {
            return null;
        }

        $accValue = $init->getValue();
        if (!is_int($accValue) && !is_float($accValue)) {
            return null;
        }

        if (!$coll instanceof VectorNode) {
            return null;
        }

        $elements = $coll->getArgs();
        if (!$this->allLiteralNodes($elements)) {
            return null;
        }

        if ($elements === []) {
            // `(reduce f init [])` is `init`. Materialise a fresh literal
            // so the source location maps to the reduce call, not the
            // init expr — keeps error reports accurate for downstream
            // passes that re-touch this node.
            return new LiteralNode($node->getEnv(), $accValue, $node->getStartSourceLocation());
        }

        foreach ($elements as $element) {
            assert($element instanceof LiteralNode);
            $eltValue = $element->getValue();
            if (!is_int($eltValue) && !is_float($eltValue)) {
                return null;
            }

            $stepResult = $this->arithmeticFolder->compute($reducerName, [$accValue, $eltValue]);
            if ($stepResult === null || is_bool($stepResult)) {
                return null;
            }

            $accValue = $stepResult;
        }

        return new LiteralNode($node->getEnv(), $accValue, $node->getStartSourceLocation());
    }

    /**
     * `(str ...)` over literal `int` / `bool` / `string` / `nil` args.
     * Floats are skipped because Phel's `val-to-str` preserves trailing
     * `.0`, handles `NaN` / `±Infinity` specially, and we don't want to
     * duplicate that surface here.
     *
     * @param list<AbstractNode> $args
     */
    public function foldStr(array $args): ?string
    {
        $parts = [];
        foreach ($args as $arg) {
            if (!$arg instanceof LiteralNode) {
                return null;
            }

            $value = $arg->getValue();
            $part = match (true) {
                $value === null => '',
                $value === true => 'true',
                $value === false => 'false',
                is_int($value) => (string) $value,
                is_string($value) => $value,
                default => null,
            };

            if ($part === null) {
                return null;
            }

            $parts[] = $part;
        }

        return implode('', $parts);
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function foldCount(array $args, CallNode $node): ?LiteralNode
    {
        if (count($args) !== 1) {
            return null;
        }

        $arg = $args[0];
        $size = match (true) {
            $arg instanceof VectorNode => count($arg->getArgs()),
            $arg instanceof SetNode => count($arg->getValues()),
            $arg instanceof MapNode => intdiv(count($arg->getKeyValues()), 2),
            default => null,
        };

        if ($size === null) {
            return null;
        }

        return new LiteralNode($node->getEnv(), $size, $node->getStartSourceLocation());
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function foldVectorPick(string $fnName, array $args, CallNode $node): ?LiteralNode
    {
        if (count($args) !== 1) {
            return null;
        }

        $arg = $args[0];
        if (!$arg instanceof VectorNode) {
            return null;
        }

        $elements = $arg->getArgs();
        if (!$this->allLiteralNodes($elements)) {
            return null;
        }

        if ($elements === []) {
            // `(first [])` is `nil` in Phel; `(last [])` is `nil` too.
            return new LiteralNode($node->getEnv(), null, $node->getStartSourceLocation());
        }

        $pick = $fnName === 'first' ? $elements[0] : $elements[count($elements) - 1];
        assert($pick instanceof LiteralNode);

        return new LiteralNode($node->getEnv(), $pick->getValue(), $node->getStartSourceLocation());
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function foldNth(array $args, CallNode $node): ?LiteralNode
    {
        if (count($args) !== 2) {
            return null;
        }

        [$target, $index] = $args;
        if (!$target instanceof VectorNode || !$index instanceof LiteralNode) {
            return null;
        }

        $idx = $index->getValue();
        if (!is_int($idx) || $idx < 0) {
            return null;
        }

        $elements = $target->getArgs();
        if (!$this->allLiteralNodes($elements)) {
            return null;
        }

        if ($idx >= count($elements)) {
            // Out-of-bounds → Phel raises at runtime. Don't lift that.
            return null;
        }

        $pick = $elements[$idx];
        assert($pick instanceof LiteralNode);

        return new LiteralNode($node->getEnv(), $pick->getValue(), $node->getStartSourceLocation());
    }

    /**
     * @param list<AbstractNode> $nodes
     */
    private function allLiteralNodes(array $nodes): bool
    {
        return array_all($nodes, static fn($n): bool => $n instanceof LiteralNode);
    }
}
