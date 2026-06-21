<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Shared\CompilerConstants;

use function count;

/**
 * Compile-time evaluation of pure core fns whose arguments are all literal.
 *
 * Folding shrinks the AST surface — the resulting `LiteralNode` skips every
 * downstream pass (call-site cache, inline expansion, emission) — so the
 * scheme intentionally errs on the side of fewer false positives:
 *
 *  - The callee must be one of the whitelisted `phel.core` fns. User-defined
 *    fns are never folded.
 *  - Every argument must already be a {@see LiteralNode} of a primitive
 *    numeric type (`int|float`). `BigInt`, `Ratio`, `BigDecimal`, strings
 *    and collection literals stay un-folded so their semantics route
 *    through the runtime numeric/equality dispatchers.
 *  - Operations that can raise at runtime (e.g. `/` by zero) are skipped
 *    so folding never converts a runtime exception into a compile-time
 *    one.
 *
 * This class is the dispatcher; the per-family fold logic lives in
 * {@see LiteralArithmeticFolder}, {@see LiteralCollectionFolder}, and
 * {@see LiteralBitwiseFolder}. Folding `if` is handled separately: it does
 * not require the test to be a core call, just a `LiteralNode`, so
 * `(if true ...)` collapses to the `then` branch without going through
 * `InvokeSymbol` at all.
 */
final readonly class ConstantFolder
{
    /** @var array<string, true> */
    private const array BOOL_PREDICATES = ['not' => true, 'nil?' => true, 'true?' => true, 'false?' => true, 'boolean' => true];

    public function __construct(
        private LiteralArithmeticFolder $arithmeticFolder = new LiteralArithmeticFolder(),
        private LiteralCollectionFolder $collectionFolder = new LiteralCollectionFolder(),
        private LiteralBitwiseFolder $bitwiseFolder = new LiteralBitwiseFolder(),
        private LiteralStringFolder $stringFolder = new LiteralStringFolder(),
    ) {}

    public function fold(CallNode $node): ?AbstractNode
    {
        $fn = $node->getFn();

        if ($fn instanceof PhpVarNode && $this->bitwiseFolder->supports($fn->getName())) {
            $result = $this->bitwiseFolder->fold($fn->getName(), $node->getArguments());
            if ($result === null) {
                return null;
            }

            return new LiteralNode($node->getEnv(), $result, $node->getStartSourceLocation());
        }

        if (!$fn instanceof GlobalVarNode) {
            return null;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return null;
        }

        $fnName = $fn->getName()->getName();

        if (isset(self::BOOL_PREDICATES[$fnName])) {
            $result = $this->foldBoolPredicate($fnName, $node->getArguments());
            if ($result === null) {
                return null;
            }

            return new LiteralNode($node->getEnv(), $result, $node->getStartSourceLocation());
        }

        $accessorResult = $this->collectionFolder->foldAccessor($fnName, $node);
        if ($accessorResult instanceof AbstractNode) {
            return $accessorResult;
        }

        if ($fnName === 'reduce') {
            $reduceResult = $this->collectionFolder->foldReduce($node);
            if ($reduceResult instanceof AbstractNode) {
                return $reduceResult;
            }
        }

        if ($fnName === 'str') {
            $strResult = $this->collectionFolder->foldStr($node->getArguments());
            if ($strResult !== null) {
                return new LiteralNode($node->getEnv(), $strResult, $node->getStartSourceLocation());
            }
        }

        if ($this->stringFolder->supports($fnName)) {
            $stringResult = $this->stringFolder->fold($fnName, $node->getArguments());
            if ($stringResult !== null) {
                return new LiteralNode($node->getEnv(), $stringResult, $node->getStartSourceLocation());
            }
        }

        $result = $this->arithmeticFolder->fold($fnName, $node->getArguments());
        if ($result === null) {
            return null;
        }

        return new LiteralNode($node->getEnv(), $result, $node->getStartSourceLocation());
    }

    /**
     * Replaces an `IfNode` whose test is a literal with the surviving branch.
     * Phel truthiness: only `null` and `false` are falsy.
     */
    public function foldIf(IfNode $node): ?AbstractNode
    {
        $test = $node->getTestExpr();
        if (!$test instanceof LiteralNode) {
            return null;
        }

        $value = $test->getValue();
        $truthy = $value !== null && $value !== false;

        return $truthy ? $node->getThenExpr() : $node->getElseExpr();
    }

    /**
     * Single-arg boolean predicates over any literal value. Phel truthiness:
     * only `nil` and `false` are falsy; everything else (including `0`,
     * `""`, empty collections) is truthy.
     *
     * @param list<AbstractNode> $args
     */
    private function foldBoolPredicate(string $fnName, array $args): ?bool
    {
        if (count($args) !== 1) {
            return null;
        }

        $arg = $args[0];
        if (!$arg instanceof LiteralNode) {
            return null;
        }

        $value = $arg->getValue();

        return match ($fnName) {
            'not' => $value === null || $value === false,
            'nil?' => $value === null,
            'true?' => $value === true,
            'false?' => $value === false,
            'boolean' => $value !== null && $value !== false,
            default => null,
        };
    }
}
