<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Shared\TagResolver;

use function array_any;
use function array_slice;
use function count;

/**
 * Lowers `(update m k (fn [v x ...] body) x ...)` with a literal one-arity
 * `fn` to the `let` the runtime `update` would have run, with the fn body
 * spliced in (#3322):
 *
 *     (let [ds m, k k, x' x ..., v (phel.core/get ds k), x x' ...]
 *       (phel.core/assoc ds k body))
 *
 * No closure is built and nothing dispatches through `phel.core/update`.
 * Target, key and extra arguments evaluate once each and in source order,
 * then the read, then the body, as they did. The params become `let`
 * bindings, so the analyzer gives them the fn's scoping for free: fresh
 * shadows, a nested `fn` capturing one, a param shadowing an outer local,
 * and destructuring, which `let` and `fn` share.
 *
 * A `let` in expression position normally emits as an IIFE, which would cost
 * what the closure cost. The lowered one is flagged to emit its bindings as
 * assignments inside the expression instead ({@see LetNode::isInlineInExpression()}).
 *
 * Shapes that keep the runtime call: a call in statement position, anything but a single-arity `fn` with a
 * param vector, a rest param, a param count that does not match the extra
 * arguments, a named fn, a tagged param or return, a `{:pre :post}` map, a
 * body mentioning `recur` (it would target the fn), and a body that could
 * write to a local of the enclosing scope, which the closure only ever saw a
 * copy of ({@see ClosureBarrierDetector}).
 *
 * @internal
 */
final readonly class UpdateLiteralFnLowering
{
    private const string CORE_NS = 'phel.core';

    private const string UPDATE = 'update';

    private const string REST_MARKER = '&';

    public function __construct(
        private ClosureBarrierDetector $barrierDetector = new ClosureBarrierDetector(),
    ) {}

    /**
     * @param PersistentListInterface<mixed> $list the whole `(update ...)` form
     */
    public function tryLower(
        GlobalVarNode $f,
        PersistentListInterface $list,
        NodeEnvironmentInterface $env,
        AnalyzerInterface $analyzer,
    ): ?AbstractNode {
        if ($f->getNamespace() !== self::CORE_NS || $f->getName()->getName() !== self::UPDATE) {
            return null;
        }

        // A discarded result gains nothing, and a typed `->put()` in
        // statement position trips the `#[\NoDiscard]` on it.
        if ($env->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            return null;
        }

        $letForm = $this->loweredForm($list);
        if (!$letForm instanceof PersistentListInterface) {
            return null;
        }

        // Whether the body writes an enclosing local shows only after macro
        // expansion. When it does, the caller analyses the call again the
        // usual way; that second pass is limited to such bodies.
        $node = $analyzer->analyze($letForm, $env);
        if ($this->barrierDetector->needsClosure($node, $env)) {
            return null;
        }

        if ($node instanceof LetNode && !$node->isLoop() && $env->isContext(NodeEnvironment::CONTEXT_EXPRESSION)) {
            return $node->withInlineInExpression();
        }

        return $node;
    }

    /**
     * @param PersistentListInterface<mixed> $list
     *
     * @return PersistentListInterface<mixed>|null
     */
    private function loweredForm(PersistentListInterface $list): ?PersistentListInterface
    {
        $count = count($list);
        if ($count < 4) {
            return null;
        }

        $fnForm = $list->get(3);
        if (!$fnForm instanceof PersistentListInterface) {
            return null;
        }

        $params = $this->singleArityParams($fnForm);
        if (!$params instanceof PersistentVectorInterface) {
            return null;
        }

        $extraArgs = array_slice($list->toArray(), 4);
        if (!$this->isSpliceableParamVector($params, count($extraArgs))) {
            return null;
        }

        $body = array_slice($fnForm->toArray(), 2);
        if ($this->hasConditionMap($body) || array_any($body, $this->mentionsRecur(...))) {
            return null;
        }

        $target = $this->gensym('ds_', $list);
        $key = $this->gensym('k_', $list);

        $bindings = [$target, $list->get(1), $key, $list->get(2)];
        $extraShadows = [];
        foreach ($extraArgs as $arg) {
            $shadow = $this->gensym('x_', $list);
            $extraShadows[] = $shadow;
            $bindings[] = $shadow;
            $bindings[] = $arg;
        }

        $paramForms = $params->toArray();
        $bindings[] = $paramForms[0];
        $bindings[] = $this->coreCall('get', [$target, $key], $list);
        foreach ($extraShadows as $i => $shadow) {
            $bindings[] = $paramForms[$i + 1];
            $bindings[] = $shadow;
        }

        // Leading body forms stay statements of the `let`; only the last one
        // is the value `assoc` writes, as it was the value the fn returned.
        $value = $body === [] ? null : $body[count($body) - 1];
        $statements = array_slice($body, 0, -1);

        return Phel::list([
            Symbol::create(Symbol::NAME_LET)->copyLocationFrom($list),
            Phel::vector($bindings)->copyLocationFrom($list),
            ...$statements,
            $this->coreCall('assoc', [$target, $key, $value], $list),
        ])->copyLocationFrom($list);
    }

    /**
     * The param vector of a `(fn [params] body...)` form, or `null` for any
     * other form, a named fn or a multi-arity one.
     *
     * @param PersistentListInterface<mixed> $form
     *
     * @return PersistentVectorInterface<mixed>|null
     */
    private function singleArityParams(PersistentListInterface $form): ?PersistentVectorInterface
    {
        if (count($form) < 2) {
            return null;
        }

        $head = $form->get(0);
        $params = $form->get(1);

        return $head instanceof Symbol
            && $head->getFullName() === Symbol::NAME_FN
            && $params instanceof PersistentVectorInterface
            ? $params
            : null;
    }

    /**
     * A tag is load-bearing on a fn: on a param it becomes a PHP parameter
     * type, on the vector a return type, and PHP enforces both. A `let`
     * binding would drop the check, so a tagged fn keeps its closure.
     *
     * @param PersistentVectorInterface<mixed> $params
     */
    private function isSpliceableParamVector(PersistentVectorInterface $params, int $extraArgCount): bool
    {
        if (count($params) !== $extraArgCount + 1 || TagResolver::fromMeta($params->getMeta()) !== null) {
            return false;
        }

        $names = [];
        foreach ($params as $param) {
            if ($param instanceof Symbol) {
                if ($param->getName() === self::REST_MARKER || isset($names[$param->getName()])) {
                    return false;
                }

                if (TagResolver::fromMeta($param->getMeta()) !== null) {
                    return false;
                }

                $names[$param->getName()] = true;
                continue;
            }

            // A destructuring pattern binds the same way under `let`.
            if (!$param instanceof PersistentVectorInterface && !$param instanceof PersistentMapInterface) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $body
     */
    private function hasConditionMap(array $body): bool
    {
        return count($body) > 1 && $body[0] instanceof PersistentMapInterface;
    }

    /**
     * Any `recur` in the body, including one a nested `loop` owns: telling
     * them apart needs the analyzer, and bailing costs only the lowering.
     */
    private function mentionsRecur(mixed $form): bool
    {
        if ($form instanceof Symbol) {
            return $form->getName() === Symbol::NAME_RECUR;
        }

        if ($form instanceof PersistentMapInterface) {
            foreach ($form as $key => $value) {
                if ($this->mentionsRecur($key) || $this->mentionsRecur($value)) {
                    return true;
                }
            }

            return false;
        }

        if ($form instanceof PersistentListInterface
            || $form instanceof PersistentVectorInterface
            || $form instanceof PersistentHashSetInterface
        ) {
            foreach ($form as $child) {
                if ($this->mentionsRecur($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function gensym(string $prefix, PersistentListInterface $list): Symbol
    {
        return Symbol::gen($prefix)->copyLocationFrom($list);
    }

    /**
     * Qualified, so neither a param nor a local named `get` or `assoc`
     * captures it: a qualified symbol never resolves to a local.
     *
     * @param list<mixed>                    $args
     * @param PersistentListInterface<mixed> $list
     *
     * @return PersistentListInterface<mixed>
     */
    private function coreCall(string $name, array $args, PersistentListInterface $list): PersistentListInterface
    {
        return Phel::list([
            Symbol::createForNamespace(self::CORE_NS, $name)->copyLocationFrom($list),
            ...$args,
        ])->copyLocationFrom($list);
    }
}
