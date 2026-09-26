<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\TailCallRewriter;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReadModel\FnSymbolTuple;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Shared\TagResolver;

use function count;
use function sprintf;

/**
 * (fn name? [params] body) or (fn name? ([params] body)+).
 *
 * Creates an anonymous function (closure). When a leading name symbol is
 * supplied (Clojure-style `(fn name ...)`), the name is bound as a local
 * inside the body so the function can refer to itself for recursion
 * (e.g. `(fn fact [n] (if (zero? n) 1 (* n (fact (dec n)))))`).
 *
 * @internal
 */
final readonly class FnSymbol implements SpecialFormAnalyzerInterface
{
    private FnPrePostConditionRewriter $prePostRewriter;

    public function __construct(
        private AnalyzerInterface $analyzer,
        bool $assertsEnabled = true,
        private ReturnTypeInferrer $returnTypeInferrer = new ReturnTypeInferrer(),
        private TailCallRewriter $tailCallRewriter = new TailCallRewriter(),
    ) {
        $this->prePostRewriter = new FnPrePostConditionRewriter($assertsEnabled);
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        if (count($list) < 2) {
            throw AnalyzerException::withLocation("'fn requires at least one argument", $list);
        }

        [$name, $list] = $this->extractOptionalName($list);

        $second = $list->get(1);
        if ($second instanceof PersistentVectorInterface) {
            return $this->analyzeSingle($list, $env, $name);
        }

        if (!($second instanceof PersistentListInterface)) {
            throw AnalyzerException::withLocation("Second argument of 'fn must be a vector", $list);
        }

        // Multi-arity defs do not get the deferred-then-grafted single walk
        // (`DefSymbol::graftInferredParamTags` only runs for single-arity
        // FnNodes), so each child must keep inferring its return type inline.
        // Children compile to methods of the multi-arity class
        // (`MultiFnAsClassEmitter`), never in the parent's own position, so
        // they are analyzed in expression context rather than inheriting a
        // return context meant for the parent.
        $childEnv = $env->withReturnInferenceDeferred(false)->withExpressionContext();

        $fnNodes = [];
        $hasVariadic = false;
        $count = count($list);
        for ($i = 1; $i < $count; ++$i) {
            $clause = $list->get($i);
            if (!($clause instanceof PersistentListInterface)) {
                throw AnalyzerException::withLocation('Invalid fn clause', $list);
            }

            $fnNode = $this->analyzeSingle(
                Phel::list([
                    Symbol::create(Symbol::NAME_FN)->copyLocationFrom($clause),
                    ...$clause->toArray(),
                ])->copyLocationFrom($clause),
                $childEnv,
                $name,
            );

            if ($name instanceof Symbol) {
                $fnNode->markAsMultiArityChild();
            }

            if ($fnNode->isVariadic()) {
                if ($hasVariadic) {
                    throw AnalyzerException::withLocation('Only one variadic overload allowed', $clause);
                }

                if ($i !== $count - 1) {
                    throw AnalyzerException::withLocation('Variadic overload must be the last one', $clause);
                }

                $hasVariadic = true;
            }

            $fnNodes[] = $fnNode;
        }

        return new MultiFnNode($env, $fnNodes, $list->getStartLocation(), $name);
    }

    /**
     * Clojure's `fn` allows an optional leading name symbol:
     *   `(fn name? [params] body)` or `(fn name? ([params] body)+)`.
     *
     * Extracts the name (if present) and returns it alongside a rebuilt list
     * that has the name removed so the rest of the pipeline can process the
     * arguments uniformly. The rebuilt list reuses the source location of the
     * original so error reporting still points at the user's form.
     *
     * @param PersistentListInterface<mixed> $list
     *
     * @return array{0: ?Symbol, 1: PersistentListInterface<mixed>}
     */
    private function extractOptionalName(PersistentListInterface $list): array
    {
        $second = $list->get(1);
        if (!($second instanceof Symbol)) {
            return [null, $list];
        }

        $elements = [];
        $count = count($list);
        for ($i = 0; $i < $count; ++$i) {
            if ($i === 1) {
                continue;
            }

            $elements[] = $list->get($i);
        }

        return [$second, Phel::list($elements)->copyLocationFrom($list)];
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function analyzeSingle(
        PersistentListInterface $list,
        NodeEnvironmentInterface $env,
        ?Symbol $name = null,
    ): FnNode {
        $list = $this->canonicalizeTags($list);
        $paramVector = $this->verifyArguments($list);

        $fnSymbolTuple = FnSymbolTuple::createWithTuple($list);
        $recurFrame = new RecurFrame($fnSymbolTuple->params());

        // If the fn's name collides with one of its parameters, the parameter
        // shadows the self-reference in the body — there is no way to reach
        // the outer fn from within its own body. Drop the self-binding so we
        // don't emit a colliding `use (&$name)` / `$name = $this;`.
        $effectiveName = $this->resolveEffectiveName($name, $fnSymbolTuple->params());

        $declaredReturnType = $this->extractReturnType($paramVector);
        $body = $this->analyzeBody($fnSymbolTuple, $recurFrame, $env, $effectiveName, $declaredReturnType === 'void');
        if ($declaredReturnType !== null) {
            $tailType = TagCompatibility::tailLiteralType($body);
            if ($tailType !== null && !TagCompatibility::accepts($declaredReturnType, $tailType)) {
                throw AnalyzerException::withLocation(
                    sprintf("Fn return type '%s' is incompatible with tail expression of type '%s'", $declaredReturnType, $tailType),
                    $list,
                );
            }
        }

        [$selfNs, $selfNameStr] = $this->splitBoundTo($env->getBoundTo());

        if ($this->analyzer->getOptimizationLevel() >= 2
            && $selfNs !== null
            && $selfNameStr !== null
            && !$fnSymbolTuple->isVariadic()
            && !$name instanceof Symbol
        ) {
            [$body] = $this->tailCallRewriter->rewrite(
                $body,
                $recurFrame,
                $selfNs,
                $selfNameStr,
                count($fnSymbolTuple->params()),
                $fnSymbolTuple->isVariadic(),
            );
        }

        // For a `def`-owned single-arity fn the return-type inference is
        // deferred to `DefSymbol::graftInferredParamTags`, which runs the
        // single walk after grafting param tags. Skip the inline walk here so
        // the body is not traversed an extra time; leave the type null for
        // `DefSymbol` to fill. A declared `:tag` always wins and never defers.
        if ($declaredReturnType === null && $env->isReturnInferenceDeferred()) {
            $returnType = null;
        } else {
            $returnType = $declaredReturnType
                ?? $this->returnTypeInferrer->infer(
                    $body,
                    $fnSymbolTuple->params(),
                    $fnSymbolTuple->isVariadic(),
                    $selfNs,
                    $selfNameStr,
                );
        }

        return new FnNode(
            $env,
            $fnSymbolTuple->params(),
            $body,
            $this->buildUsesFromEnv($env, $fnSymbolTuple, $effectiveName),
            $fnSymbolTuple->isVariadic(),
            $recurFrame->isActive(),
            $list->getStartLocation(),
            $effectiveName,
            $returnType,
        );
    }

    /**
     * Splits a `boundTo` string of the form `"namespace\\name"` (set by
     * `DefSymbol` when analyzing a `(defn ...)` body) into the analyzer's
     * dot-separated namespace + bare name. Returns `[null, null]` when
     * the fn is anonymous: cross-fn self-skip needs both halves to fire.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function splitBoundTo(string $boundTo): array
    {
        if ($boundTo === '') {
            return [null, null];
        }

        $pos = strrpos($boundTo, '\\');
        if ($pos === false) {
            return [null, null];
        }

        $ns = str_replace('\\', '.', substr($boundTo, 0, $pos));
        $name = substr($boundTo, $pos + 1);
        if ($ns === '' || $name === '') {
            return [null, null];
        }

        return [$ns, $name];
    }

    /**
     * Resolves the params' and return tag's `:use` imports against this
     * namespace before anything reads them ({@see TagCanonicalizer}).
     *
     * @param PersistentListInterface<mixed> $list
     *
     * @return PersistentListInterface<mixed>
     */
    private function canonicalizeTags(PersistentListInterface $list): PersistentListInterface
    {
        $paramVector = $this->verifyArguments($list);
        $canonical = TagCanonicalizer::paramVector($paramVector, $this->analyzer);
        if ($canonical === $paramVector) {
            return $list;
        }

        $elements = $list->toArray();
        $elements[1] = $canonical;

        return Phel::list($elements)
            ->copyLocationFrom($list)
            ->withMeta($list->getMeta());
    }

    /**
     * @param PersistentVectorInterface<mixed> $paramVector
     */
    private function extractReturnType(PersistentVectorInterface $paramVector): ?string
    {
        return TagResolver::fromMeta($paramVector->getMeta());
    }

    /**
     * @param list<Symbol> $params
     */
    private function resolveEffectiveName(?Symbol $name, array $params): ?Symbol
    {
        if (!$name instanceof Symbol) {
            return null;
        }

        foreach ($params as $param) {
            if ($param->getName() === $name->getName()) {
                return null;
            }
        }

        return $name;
    }

    /**
     * @param PersistentListInterface<mixed> $list
     *
     * @return PersistentVectorInterface<mixed>
     */
    private function verifyArguments(PersistentListInterface $list): PersistentVectorInterface
    {
        if (count($list) < 2) {
            throw AnalyzerException::withLocation("'fn requires at least one argument", $list);
        }

        $paramVector = $list->get(1);
        if (!$paramVector instanceof PersistentVectorInterface) {
            throw AnalyzerException::withLocation("Second argument of 'fn must be a vector", $list);
        }

        return $paramVector;
    }

    /**
     * A `^void` fn compiles to a PHP `: void` function, which may not return a
     * value, so its body is analysed in statement context: the tail runs for
     * effect and the call answers nil (#3363).
     */
    private function analyzeBody(
        FnSymbolTuple $fnSymbolTuple,
        RecurFrame $recurFrame,
        NodeEnvironmentInterface $env,
        ?Symbol $name = null,
        bool $isVoid = false,
    ): AbstractNode {
        $listBody = $fnSymbolTuple->parentListBody();

        $body = $this->prePostRewriter->rewrite(
            $listBody,
            fn(array $strippedBody): PersistentListInterface => $fnSymbolTuple->lets() === []
                ? $this->createDoTupleWithBody($strippedBody)
                : $this->createLetTupleWithBody($fnSymbolTuple, $strippedBody),
        );

        $locals = $fnSymbolTuple->params();
        if ($name instanceof Symbol) {
            // Bind the fn's own name as a local inside the body so self-recursion
            // resolves to a LocalVarNode (which the emitter turns into the
            // self-binding variable / $this) instead of a global lookup.
            $locals = [$name, ...$locals];
        }

        $withLocals = $env->withMergedLocals($locals);
        $bodyEnv = ($isVoid ? $withLocals->withStatementContext() : $withLocals->withReturnContext())
            ->withAddedRecurFrame($recurFrame)
            // The deferral applies only to this fn's own return type; nested
            // fns inside the body have no grafting step, so they must keep
            // inferring their return type inline.
            ->withReturnInferenceDeferred(false)
            // A fn body is a PHP frame of its own.
            ->withinTry(false);

        return $this->analyzer->analyze($body, $bodyEnv);
    }

    /**
     * @param array<int, mixed> $body
     *
     * @return PersistentListInterface<mixed>
     */
    private function createDoTupleWithBody(array $body): PersistentListInterface
    {
        return Phel::list([
            (Symbol::create(Symbol::NAME_DO))->copyLocationFrom($body),
            ...$body,
        ])->copyLocationFrom($body);
    }

    /**
     * @param array<int, mixed> $listBody
     *
     * @return PersistentListInterface<mixed>
     */
    private function createLetTupleWithBody(FnSymbolTuple $fnSymbolTuple, array $listBody): PersistentListInterface
    {
        return Phel::list([
            (Symbol::create(Symbol::NAME_LET))->copyLocationFrom($listBody),
            Phel::vector($fnSymbolTuple->lets())->copyLocationFrom($listBody),
            ...$listBody,
        ])->copyLocationFrom($listBody);
    }

    /**
     * @return list<Symbol>
     */
    private function buildUsesFromEnv(
        NodeEnvironmentInterface $env,
        FnSymbolTuple $fnSymbolTuple,
        ?Symbol $name = null,
    ): array {
        $excluded = $fnSymbolTuple->params();
        if ($name instanceof Symbol) {
            // The fn's own name is introduced into the body's local scope but
            // must never be captured as a `use (...)` for the compiled closure:
            // it is provided by the self-binding emission path instead.
            $excluded = [$name, ...$excluded];
        }

        return array_values(array_diff($env->getLocals(), $excluded));
    }

}
