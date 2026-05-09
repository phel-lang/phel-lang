<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

use function count;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Conservative tail-position type inference for `fn` bodies. Walks the
 * body's tail expression looking for a primitive PHP operator on
 * already-typed locals or scalar literals, and surfaces the implied
 * PHP type so the emitter can stamp it on the compiled signature.
 *
 * Inference is intentionally narrow: any non-recognized node, an
 * untyped local, or a branch disagreement returns `null`, leaving the
 * fn's return type unset. Explicit `:tag` declarations on the param
 * vector or the def name take precedence — this class only fills the
 * gap when the user has not annotated.
 */
final class ReturnTypeInferrer
{
    /**
     * Sentinel for nodes that do not produce a runtime value (`recur`,
     * `throw`). Used to merge `if` branches where one side is bottom
     * and the other yields a concrete type.
     */
    private const string BOTTOM = '@bottom';

    /** @var array<string, string> */
    private const array COMPARISON_OPS = [
        '<' => 'bool', '>' => 'bool', '<=' => 'bool', '>=' => 'bool',
        '==' => 'bool', '===' => 'bool', '!=' => 'bool', '!==' => 'bool',
        '<=>' => 'int', 'instanceof' => 'bool',
    ];

    /** @var list<string> */
    private const array NUMERIC_OPS = [
        '+', '-', '*', '/', '%', '**', '<<', '>>', '|', '&', '^',
    ];

    /**
     * Set during a walk when a primitive PHP operator is encountered.
     * Only then does the function publish an inferred return type;
     * pure literal / pass-through bodies stay untyped so the emitter
     * does not synthesize an annotation the user never asked for.
     */
    private bool $sawOperator = false;

    /**
     * @param list<Symbol> $params
     *
     * @psalm-suppress RedundantCondition `inferNode` mutates `sawOperator`
     */
    public function infer(AbstractNode $body, array $params, bool $isVariadic = false): ?string
    {
        $paramTypes = $this->collectParamTypes($params, $isVariadic);
        $this->sawOperator = false;
        $type = $this->inferNode($body, $paramTypes);

        if (!$this->sawOperator) {
            return null;
        }

        return $type === self::BOTTOM ? null : $type;
    }

    /**
     * The variadic tail's `:tag` describes the element type, not the
     * value bound in the body (which is a Phel `Vector`). Skip it so
     * the inferrer does not see a fake int/string local where the
     * runtime actually carries a collection.
     *
     * @param list<Symbol> $params
     *
     * @return array<string, string>
     */
    private function collectParamTypes(array $params, bool $isVariadic): array
    {
        $types = [];
        $lastIndex = $isVariadic ? count($params) - 1 : count($params);
        for ($i = 0; $i < $lastIndex; ++$i) {
            $param = $params[$i];
            $tag = $this->extractTag($param);
            if ($tag !== null) {
                $types[$param->getName()] = $tag;
            }
        }

        return $types;
    }

    /**
     * @param array<string, string> $locals
     *
     * @phpstan-impure mutates `sawOperator` when a primitive op fires
     */
    private function inferNode(AbstractNode $node, array $locals): ?string
    {
        return match (true) {
            $node instanceof DoNode => $this->inferDo($node, $locals),
            $node instanceof IfNode => $this->inferIf($node, $locals),
            $node instanceof LetNode => $this->inferLet($node, $locals),
            $node instanceof CallNode => $this->inferCall($node, $locals),
            $node instanceof LiteralNode => $this->inferLiteral($node),
            $node instanceof LocalVarNode => $locals[$node->getName()->getName()] ?? null,
            $node instanceof RecurNode => $this->inferRecur($node, $locals),
            $node instanceof ThrowNode => self::BOTTOM,
            default => null,
        };
    }

    /**
     * @param array<string, string> $locals
     */
    private function inferDo(DoNode $node, array $locals): ?string
    {
        return $this->inferNode($node->getRet(), $locals);
    }

    /**
     * @param array<string, string> $locals
     */
    private function inferIf(IfNode $node, array $locals): ?string
    {
        // Walk the test for its operator side-effect: `(if (php/< x 0) ...)`
        // should mark the inference triggered even though the test's type
        // never reaches the return slot.
        $this->inferNode($node->getTestExpr(), $locals);
        $then = $this->inferNode($node->getThenExpr(), $locals);
        $else = $this->inferNode($node->getElseExpr(), $locals);
        if ($then === null || $else === null) {
            return null;
        }

        if ($then === self::BOTTOM) {
            return $else;
        }

        if ($else === self::BOTTOM) {
            return $then;
        }

        return $then === $else ? $then : null;
    }

    /**
     * @param array<string, string> $locals
     */
    private function inferRecur(RecurNode $node, array $locals): string
    {
        // Recur jumps; it never produces a value. Walk the rebinding
        // expressions so any operator hidden in them still trips the
        // `sawOperator` gate.
        foreach ($node->getExpressions() as $expr) {
            $this->inferNode($expr, $locals);
        }

        return self::BOTTOM;
    }

    /**
     * @param array<string, string> $locals
     */
    private function inferLet(LetNode $node, array $locals): ?string
    {
        foreach ($node->getBindings() as $binding) {
            $type = $this->inferNode($binding->getInitExpr(), $locals);
            if ($type !== null && $type !== self::BOTTOM) {
                $locals[$this->bindingName($binding)] = $type;
            }
        }

        return $this->inferNode($node->getBodyExpr(), $locals);
    }

    /**
     * @param array<string, string> $locals
     */
    private function inferCall(CallNode $node, array $locals): ?string
    {
        $fn = $node->getFn();
        if (!$fn instanceof PhpVarNode) {
            return null;
        }

        $op = $fn->getName();

        if (isset(self::COMPARISON_OPS[$op])) {
            $this->sawOperator = true;
            return self::COMPARISON_OPS[$op];
        }

        if ($op === '.') {
            $this->sawOperator = true;
            return 'string';
        }

        if (in_array($op, self::NUMERIC_OPS, true)) {
            $this->sawOperator = true;
            return $this->inferNumeric($node, $locals);
        }

        return null;
    }

    /**
     * @param array<string, string> $locals
     */
    private function inferNumeric(CallNode $node, array $locals): ?string
    {
        $hasFloat = false;
        foreach ($node->getArguments() as $arg) {
            $type = $this->inferNode($arg, $locals);
            if ($type === null || $type === self::BOTTOM) {
                return null;
            }

            if ($type === 'float') {
                $hasFloat = true;
                continue;
            }

            if ($type !== 'int') {
                return null;
            }
        }

        return $hasFloat ? 'float' : 'int';
    }

    private function inferLiteral(LiteralNode $node): ?string
    {
        $value = $node->getValue();
        return match (true) {
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_bool($value) => 'bool',
            is_string($value) => 'string',
            default => null,
        };
    }

    private function extractTag(Symbol $param): ?string
    {
        $meta = $param->getMeta();
        if (!$meta instanceof PersistentMapInterface) {
            return null;
        }

        $tag = $meta->find(Keyword::create('tag'));
        if ($tag instanceof Symbol) {
            return $tag->getName();
        }

        return is_string($tag) && $tag !== '' ? $tag : null;
    }

    private function bindingName(BindingNode $binding): string
    {
        return $binding->getShadow()->getName();
    }
}
