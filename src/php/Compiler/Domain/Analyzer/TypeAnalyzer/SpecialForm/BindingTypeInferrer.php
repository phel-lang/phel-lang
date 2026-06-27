<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;

use function array_map;
use function count;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Infers a primitive `:tag` (`int` / `float` / `bool` / `string`) for
 * `let` / `loop` bindings from their already-analyzed init expression and
 * grafts it onto the binding symbol — the very `:tag` a hand-written
 * `^int` annotation would carry. With the tag present,
 * {@see LocalVarNode::getInferredType()} reports the type and the emitter
 * lowers `(+ x 1)` / `(< x n)` / `(= x y)` over the binding to a native
 * PHP operator instead of the runtime `phel.core` dispatch.
 *
 * Without this, only `defn` params (via {@see ParamTypeInferrer}) and
 * explicit annotations carry a tag, so a morally-int binding such as
 * `(let [n (php/count v)] (+ n 1))` leaves `n` untyped and every op over
 * it stays on runtime dispatch — which is why hot code reaches for raw
 * `php/` interop operators.
 *
 * Inference is intentionally conservative: any binding not provably one of
 * the primitive tags is left untouched. A missing tag only ever costs a
 * (correct) runtime dispatch; it never produces a wrong lowering. An
 * inferred tag is byte-identical to the same tag written by hand, so it
 * inherits the emitter's existing specialization guards verbatim — including
 * the accepted policy that a native `+`/`-`/`*` on an int operand yields a
 * PHP float past `PHP_INT_MAX` where the runtime would promote to `BigInt`
 * (identical to an explicit `^int` and to `inc`/`dec`).
 */
final class BindingTypeInferrer
{
    /** @var list<string> */
    private const array PRIMITIVE_TAGS = ['int', 'float', 'bool', 'string'];

    /**
     * `php/<op>` binary ops whose result type is exactly PHP's: `int` when
     * every operand is int, `float` when any operand is float. Restricted to
     * `+ - *` — `/` (`5/2 → float`), `%`, `**` and the bit ops can change the
     * result category in ways a single fixed tag would misstate.
     *
     * @var list<string>
     */
    private const array NUMERIC_BINARY_OPS = ['+', '-', '*'];

    /**
     * `phel.core` arity-1 ops whose result type is the operand's numeric type
     * — so `(recur (inc i))` over an int counter keeps the binding int.
     *
     * @var list<string>
     */
    private const array CORE_INC_DEC_OPS = ['inc', 'dec'];

    /** @var array<string, string> */
    private const array COMPARISON_OPS = [
        '<' => 'bool', '>' => 'bool', '<=' => 'bool', '>=' => 'bool',
        '==' => 'bool', '===' => 'bool', '!=' => 'bool', '!==' => 'bool',
    ];

    /**
     * Graft inferred tags onto a `let`'s bindings. Processed in order so a
     * later binding's init can read an earlier sibling's freshly grafted tag.
     *
     * @param list<BindingNode> $bindings
     */
    public function graftLetBindings(array $bindings): void
    {
        foreach ($bindings as $binding) {
            $this->graftBinding($binding, $this->typeOf($binding->getInitExpr()));
        }
    }

    /**
     * Graft inferred tags onto a `loop`'s bindings. A loop binding is
     * rebindable via `recur`, so a tag is kept only when the init type AND
     * every `recur` argument for that slot agree on one primitive; on any
     * disagreement or unknown arm the binding is left untyped, so the body
     * never sees an over-narrow tag.
     *
     * MUST run after the loop body is analyzed — the `recur` nodes live in
     * the body — but the in-place graft is still visible to the body's
     * already-built {@see LocalVarNode}s at emit time.
     *
     * @param list<BindingNode> $bindings
     */
    public function graftLoopBindings(array $bindings, AbstractNode $body): void
    {
        // Seed each binding's type (a user-written tag, else the inferred init
        // type) first, so a recur arg that reads a sibling counter (or the
        // binding itself, e.g. `(recur (+ i 1))`) types against the loop's own
        // bindings — the fixpoint order the return-type inferrer also relies on.
        $seeded = [];
        foreach ($bindings as $binding) {
            $type = $this->declaredTag($binding->getSymbol()) ?? $this->typeOf($binding->getInitExpr());
            if ($type !== null) {
                $seeded[$binding->getShadow()->getName()] = $type;
            }
        }

        if ($seeded === []) {
            return;
        }

        $recurArgTypes = [];
        $this->collectRecurArgTypes($body, $seeded, $recurArgTypes);

        foreach ($bindings as $i => $binding) {
            $name = $binding->getShadow()->getName();
            if (!isset($seeded[$name])) {
                continue;
            }

            // A user-written tag wins unconditionally (the author owns the
            // type); an inferred one survives only when the init and every
            // recur arm agree, else the binding stays untyped so the body
            // never sees an over-narrow tag.
            if ($this->declaredTag($binding->getSymbol()) === null
                && !$this->recurArgsAgree($recurArgTypes[$i] ?? null, $seeded[$name])
            ) {
                continue;
            }

            $this->graftBinding($binding, $seeded[$name]);
        }
    }

    /**
     * `null` means no `recur` rebinds this slot (it keeps its init value every
     * iteration, so the init type is exact). Otherwise every recorded arm must
     * be the init type; an unknown arm (`null` element) counts as disagreement.
     *
     * Multiple arms that all agree are fine — unlike `ReturnTypeInferrer`'s
     * stricter return-type rule, which drops a slot rebound from more than one
     * `recur` site outright. Two arms agreeing on the same primitive cannot make
     * the binding any other type, so keeping the tag is sound here.
     *
     * @param list<?string>|null $alts
     */
    private function recurArgsAgree(?array $alts, string $initType): bool
    {
        if ($alts === null) {
            return true;
        }

        return array_all($alts, static fn($alt): bool => $alt === $initType);
    }

    /**
     * Walks tail-position descendants for `recur` invocations targeting the
     * enclosing loop and records each argument's inferred type per binding
     * slot. Nested loops own their own `recur`s and are skipped.
     *
     * @param array<string, string>     $locals
     * @param array<int, list<?string>> $types
     */
    private function collectRecurArgTypes(AbstractNode $node, array $locals, array &$types): void
    {
        if ($node instanceof RecurNode) {
            foreach ($node->getExpressions() as $i => $expr) {
                $types[$i][] = $this->typeOf($expr, $locals);
            }

            return;
        }

        if ($node instanceof IfNode) {
            $this->collectRecurArgTypes($node->getThenExpr(), $locals, $types);
            $this->collectRecurArgTypes($node->getElseExpr(), $locals, $types);
            return;
        }

        if ($node instanceof DoNode) {
            $this->collectRecurArgTypes($node->getRet(), $locals, $types);
            return;
        }

        if ($node instanceof LetNode && !$node->isLoop()) {
            $this->collectRecurArgTypes($node->getBodyExpr(), $locals, $types);
        }
    }

    /**
     * Primitive tag of an already-analyzed expression node, or `null` when it
     * is not statically one of {@see self::PRIMITIVE_TAGS}. `$locals` maps a
     * loop binding's shadow name to its seeded type for `recur` arg typing.
     *
     * @param array<string, string> $locals
     */
    private function typeOf(AbstractNode $node, array $locals = []): ?string
    {
        if ($node instanceof LiteralNode) {
            return $this->literalType($node->getValue());
        }

        if ($node instanceof LocalVarNode) {
            return $locals[$node->getName()->getName()] ?? $this->primitiveInferredType($node);
        }

        if ($node instanceof CallNode) {
            return $this->callType($node, $locals);
        }

        return null;
    }

    /**
     * @param array<string, string> $locals
     */
    private function callType(CallNode $node, array $locals): ?string
    {
        $fn = $node->getFn();
        return match (true) {
            $fn instanceof PhpVarNode => $this->phpCallType($fn->getName(), $node, $locals),
            $fn instanceof GlobalVarNode => $this->coreCallType($fn, $node, $locals),
            default => null,
        };
    }

    /**
     * @param array<string, string> $locals
     */
    private function phpCallType(string $op, CallNode $node, array $locals): ?string
    {
        if (isset(self::COMPARISON_OPS[$op])) {
            return self::COMPARISON_OPS[$op];
        }

        if (in_array($op, self::NUMERIC_BINARY_OPS, true)) {
            return $this->numericResultType($node, $locals);
        }

        return PurePhpFunctionReturnTypes::returnTypeOf($op);
    }

    /**
     * `phel.core` arithmetic over already-typed operands — so a binding like
     * `(let [d (+ a b)] ...)` or a `(recur (+ i 1))` counter types from its
     * operands the same way the `php/` interop ops do. Restricted to the
     * operand-type-preserving ops (`+ - *`, `inc`/`dec`); polymorphic helpers
     * (`/`, `quot`, `min`, …) are left untyped.
     *
     * @param array<string, string> $locals
     */
    private function coreCallType(GlobalVarNode $fn, CallNode $node, array $locals): ?string
    {
        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return null;
        }

        $name = $fn->getName()->getName();

        if (in_array($name, self::NUMERIC_BINARY_OPS, true)) {
            return $this->numericResultType($node, $locals);
        }

        if (in_array($name, self::CORE_INC_DEC_OPS, true)) {
            $args = $node->getArguments();
            if (count($args) !== 1) {
                return null;
            }

            $type = $this->typeOf($args[0], $locals);
            return ArithmeticResultType::isFloatOrInt($type) ? $type : null;
        }

        return null;
    }

    /**
     * `int` when every operand is int, `float` when any is float; `null` if an
     * operand is not statically numeric (so the binding stays on dispatch).
     *
     * @param array<string, string> $locals
     */
    private function numericResultType(CallNode $node, array $locals): ?string
    {
        return ArithmeticResultType::fromOperands(
            array_map(fn(AbstractNode $arg): ?string => $this->typeOf($arg, $locals), $node->getArguments()),
        );
    }

    private function primitiveInferredType(LocalVarNode $node): ?string
    {
        $type = $node->getInferredType();
        return in_array($type, self::PRIMITIVE_TAGS, true) ? $type : null;
    }

    private function literalType(mixed $value): ?string
    {
        return match (true) {
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_bool($value) => 'bool',
            is_string($value) => 'string',
            default => null,
        };
    }

    /**
     * Apply a binding's effective tag. `$inferredType` is the type derived from
     * the init (or `recur`-agreed type for a loop); a user-written `:tag` on the
     * binding symbol takes precedence over it.
     */
    private function graftBinding(BindingNode $binding, ?string $inferredType): void
    {
        $declared = $this->declaredTag($binding->getSymbol());
        $tag = $declared ?? $inferredType;
        if ($tag === null) {
            return;
        }

        // Stamp an inferred tag on the binding symbol for the emitter's
        // `/** @var T */` doctag; a user-written tag is already there.
        if ($declared === null) {
            $this->putTag($binding->getSymbol(), $tag);
        }

        // Mirror onto the shadow — the unique instance a reference resolves to
        // (LetEmitter names the variable after it). Binding the tag there lets
        // `LocalVarNode::getInferredType` read it exactly, so a name reused in a
        // nested scope no longer inherits the outer binding's tag.
        $this->putTag($binding->getShadow(), $tag);
    }

    private function declaredTag(Symbol $symbol): ?string
    {
        $meta = $symbol->getMeta();
        if (!$meta instanceof PersistentMapInterface) {
            return null;
        }

        $tag = $meta->find(Keyword::create('tag'));
        if ($tag instanceof Symbol) {
            $tag = $tag->getName();
        }

        return is_string($tag) && $tag !== '' ? $tag : null;
    }

    private function putTag(Symbol $symbol, string $type): void
    {
        $merged = ($symbol->getMeta() ?? Phel::map())->put(Keyword::create('tag'), Symbol::create($type));
        $symbol->withMeta($merged);
    }
}
