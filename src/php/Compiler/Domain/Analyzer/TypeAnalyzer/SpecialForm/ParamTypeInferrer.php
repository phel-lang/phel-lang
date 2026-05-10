<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Lang\Symbol;

use function array_unique;
use function count;
use function in_array;
use function is_float;

/**
 * Walks a fn body to surface conservative param-type contracts that the
 * static type checker can use to flag obvious call-site mismatches. The
 * inferred map is advisory only: it never reaches the emitter, so the
 * compiled PHP signature stays untyped and runtime coercion behaviour is
 * preserved.
 *
 * Inference is deliberately narrow. A param earns a tag only when every
 * use across every reached branch agrees on the same primitive. Any
 * operator that coerces (comparisons), any nested fn body (closures own
 * their own params), or any disagreement drops the param so the checker
 * never raises a spurious diagnostic.
 */
final class ParamTypeInferrer
{
    /** @var list<string> */
    private const array NUMERIC_OPS = [
        '+', '-', '*', '/', '%', '**', '<<', '>>', '|', '&', '^',
    ];

    private const string STRING_CONCAT_OP = '.';

    /**
     * Globals that signal "the function defensively rejects bad inputs at
     * runtime". A param threaded through any of these escapes inference
     * even when later used by a primitive op, so deliberate negative tests
     * (e.g. `(bit-and nil 1)` against an `assert-non-nil` guard) keep
     * compiling and reach the runtime guard.
     *
     * @var list<string>
     */
    private const array GUARD_GLOBALS = [
        'assert-non-nil',
        'assert',
    ];

    /** @var array<string, list<string>> */
    private array $observations = [];

    /** @var array<string, true> */
    private array $params = [];

    /** @var array<string, true> */
    private array $guarded = [];

    /**
     * @param list<Symbol> $params
     *
     * @return array<string, string>
     */
    public function infer(AbstractNode $body, array $params, bool $isVariadic = false): array
    {
        $this->observations = [];
        $this->params = [];
        $this->guarded = [];

        $lastIndex = $isVariadic ? count($params) - 1 : count($params);
        for ($i = 0; $i < $lastIndex; ++$i) {
            // The variadic tail binds a `Vector`, not a scalar; excluding
            // it keeps numeric/string observations from constraining the
            // wrong runtime shape.
            $this->params[$params[$i]->getName()] = true;
        }

        if ($this->params === []) {
            return [];
        }

        $this->walk($body);

        $result = [];
        foreach ($this->observations as $name => $types) {
            if (isset($this->guarded[$name])) {
                continue;
            }

            $unique = array_unique($types);
            if (count($unique) === 1) {
                $result[$name] = $unique[0];
            }
        }

        return $result;
    }

    private function walk(AbstractNode $node): void
    {
        if ($node instanceof FnNode) {
            // Closures own their own params; a `$x` inside is unrelated
            // to the outer fn's `$x`.
            return;
        }

        if ($node instanceof DoNode) {
            foreach ($node->getStmts() as $stmt) {
                $this->walk($stmt);
            }

            $this->walk($node->getRet());
            return;
        }

        if ($node instanceof IfNode) {
            $this->walk($node->getTestExpr());
            $this->walk($node->getThenExpr());
            $this->walk($node->getElseExpr());
            return;
        }

        if ($node instanceof LetNode) {
            foreach ($node->getBindings() as $binding) {
                $this->walk($binding->getInitExpr());
            }

            $this->walk($node->getBodyExpr());
            return;
        }

        if ($node instanceof RecurNode) {
            // recur rebinds loop locals positionally; we can't constrain
            // the bound names from the call site without tracking the
            // matching loop frame, so just walk arg expressions for any
            // operator usage they contain.
            foreach ($node->getExpressions() as $expr) {
                $this->walk($expr);
            }

            return;
        }

        if ($node instanceof ThrowNode) {
            return;
        }

        if ($node instanceof CallNode) {
            $this->walkCall($node);
            return;
        }
    }

    private function walkCall(CallNode $node): void
    {
        $fn = $node->getFn();
        // Recurse into the callee position first: descending captures any
        // operator hidden in a higher-order arg without blocking the
        // fn-position itself from acting as a constraint source.
        $this->walk($fn);

        if ($fn instanceof GlobalVarNode && $this->isGuardGlobal($fn)) {
            $this->walkArgs($node, fn(AbstractNode $a) => $this->markGuarded($a));
            return;
        }

        if (!$fn instanceof PhpVarNode) {
            $this->walkArgs($node);
            return;
        }

        $op = $fn->getName();

        if ($op === self::STRING_CONCAT_OP) {
            $this->walkArgs($node, fn(AbstractNode $a) => $this->constrainArgAsScalar($a, 'string'));
            return;
        }

        if (in_array($op, self::NUMERIC_OPS, true)) {
            $this->walkNumericCall($node);
            return;
        }

        // Everything else (comparisons, `aget`, unknown PHP fns) walks
        // arg expressions for nested operators without constraining the
        // local: PHP comparisons coerce both sides at runtime, and
        // unknown functions could accept anything.
        $this->walkArgs($node);
    }

    private function walkNumericCall(CallNode $node): void
    {
        $args = $node->getArguments();
        $hasFloat = array_any($args, static fn($arg): bool => $arg instanceof LiteralNode && is_float($arg->getValue()));

        $type = $hasFloat ? 'float' : 'int';
        $this->walkArgs($node, fn(AbstractNode $a) => $this->constrainArgAsScalar($a, $type));
    }

    /**
     * @param (callable(AbstractNode): void)|null $observe
     */
    private function walkArgs(CallNode $node, ?callable $observe = null): void
    {
        foreach ($node->getArguments() as $arg) {
            if ($observe !== null) {
                $observe($arg);
            }

            $this->walk($arg);
        }
    }

    private function constrainArgAsScalar(AbstractNode $arg, string $type): void
    {
        $name = $this->paramNameOf($arg);
        if ($name !== null) {
            $this->observations[$name][] = $type;
        }
    }

    private function markGuarded(AbstractNode $arg): void
    {
        $name = $this->paramNameOf($arg);
        if ($name !== null) {
            $this->guarded[$name] = true;
        }
    }

    private function paramNameOf(AbstractNode $arg): ?string
    {
        if (!$arg instanceof LocalVarNode) {
            return null;
        }

        $name = $arg->getName()->getName();
        return isset($this->params[$name]) ? $name : null;
    }

    private function isGuardGlobal(GlobalVarNode $fn): bool
    {
        return in_array($fn->getName()->getName(), self::GUARD_GLOBALS, true);
    }
}
