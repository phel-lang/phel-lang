<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Api\Location;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Throwable;

use function count;
use function in_array;
use function mb_strlen;
use function str_contains;

/**
 * Resolves the lexical binding a cursor sits on to the symbol that introduced
 * it.
 *
 * The walk is lexical, reader-level and best-effort: it runs on raw forms, so
 * every binder has to be modelled by hand and nothing here is macroexpanded.
 * An unparseable buffer contributes nothing, and a `Throwable` mid-walk
 * degrades to "not a local binding" rather than failing the navigation request.
 *
 * Its guiding rule is to be right or to be silent: a form whose binding shape
 * is not modelled ({@see self::BARRIER_FORMS}) still hides the names it
 * rebinds, so a usage inside it never resolves to an outer binding of the same
 * name. `null` lets the caller fall back to the project index.
 *
 * @internal
 */
final readonly class LocalBindingResolver
{
    /** `(head [pattern init ...] body ...)`, where each init sees the bindings before it. */
    private const array PAIRWISE_FORMS = [
        Symbol::NAME_LET,
        Symbol::NAME_LOOP,
    ];

    /** `(head [pattern test] body ...)`: macros over a single `let` pair. */
    private const array SINGLE_BINDING_FORMS = [
        'if-let',
        'when-let',
        'if-some',
        'when-some',
        'when-first',
    ];

    /** Of those, the ones whose trailing `else` expands outside the binding. */
    private const array ELSE_BRANCH_FORMS = [
        'if-let',
        'if-some',
    ];

    /**
     * Binders whose shape is not modelled: `for`/`dofor` interleave patterns,
     * verbs and expressions, `defn` may be multi-arity, and `binding` rebinds
     * existing dynamic vars rather than introducing locals. Resolving inside
     * them would risk a confidently wrong jump, so a name they rebind resolves
     * to nothing at all.
     */
    private const array BARRIER_FORMS = [
        'defn',
        'defn-',
        'defmacro',
        'defmacro-',
        'for',
        'dofor',
        'binding',
    ];

    private const string REST_MARKER = '&';

    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    /**
     * @param int $line 1-based line of the cursor
     * @param int $col  1-based column of the cursor
     */
    public function resolve(string $source, string $uri, int $line, int $col, string $word): ?Location
    {
        // Locals are never qualified, and `Symbol::getName()` never contains a
        // `/`, so a qualified word cannot match one. Bailing here skips the
        // whole-buffer parse below, which dominates the cost of this call.
        if (str_contains($word, '/')) {
            return null;
        }

        try {
            foreach ($this->compilerFacade->readFormsBestEffort($source, $uri) as $form) {
                $binding = $this->walk($form, $line, $col, $word, []);
                if ($binding instanceof Symbol) {
                    return $this->toLocation($uri, $binding);
                }
            }
        } catch (Throwable) {
            // Best-effort: no local binding is better than a broken editor.
        }

        return null;
    }

    /**
     * @param list<Symbol> $scope innermost-last list of binding symbols in scope
     */
    private function walk(mixed $form, int $line, int $col, string $word, array $scope): ?Symbol
    {
        if ($form instanceof Symbol) {
            if (!$this->isAt($form, $line, $col) || $form->getName() !== $word) {
                return null;
            }

            return $this->lookupScope($scope, $word);
        }

        if ($form instanceof PersistentListInterface) {
            return $this->walkList($form, $line, $col, $word, $scope);
        }

        if ($form instanceof PersistentVectorInterface || $form instanceof PersistentHashSetInterface) {
            foreach ($form as $child) {
                $binding = $this->walk($child, $line, $col, $word, $scope);
                if ($binding instanceof Symbol) {
                    return $binding;
                }
            }

            return null;
        }

        if ($form instanceof PersistentMapInterface) {
            foreach ($form as $key => $value) {
                $binding = $this->walk($key, $line, $col, $word, $scope)
                    ?? $this->walk($value, $line, $col, $word, $scope);
                if ($binding instanceof Symbol) {
                    return $binding;
                }
            }

            return null;
        }

        return null;
    }

    /**
     * @param PersistentListInterface<mixed> $form
     * @param list<Symbol>                   $scope
     */
    private function walkList(PersistentListInterface $form, int $line, int $col, string $word, array $scope): ?Symbol
    {
        if (!$this->pointInside($form, $line, $col)) {
            return null;
        }

        $head = count($form) > 0 ? $form->get(0) : null;
        // The compiler dispatches special forms by their full name, so a
        // qualified `(my/let ...)` is an ordinary call.
        $name = $head instanceof Symbol && $head->getNamespace() === null
            ? $head->getName()
            : null;

        if ($name === Symbol::NAME_QUOTE) {
            // Quoted forms are inert data: their `let` heads bind nothing.
            return null;
        }

        if (in_array($name, self::PAIRWISE_FORMS, true)) {
            return $this->walkPairwiseForm($form, $line, $col, $word, $scope);
        }

        if (in_array($name, self::SINGLE_BINDING_FORMS, true)) {
            return $this->walkSingleBindingForm($form, $line, $col, $word, $scope);
        }

        if ($name === Symbol::NAME_FN) {
            return $this->walkParameterForm($form, $line, $col, $word, $scope, trailingInitCount: 0);
        }

        if ($name === Symbol::NAME_FOREACH) {
            // The binding vector's last element is the collection expression.
            return $this->walkParameterForm($form, $line, $col, $word, $scope, trailingInitCount: 1);
        }

        if ($name === Symbol::NAME_CATCH) {
            return $this->walkCatchForm($form, $line, $col, $word, $scope);
        }

        if (in_array($name, self::BARRIER_FORMS, true) && $this->rebinds($form, $word)) {
            return null;
        }

        foreach ($form as $child) {
            $binding = $this->walk($child, $line, $col, $word, $scope);
            if ($binding instanceof Symbol) {
                return $binding;
            }
        }

        return null;
    }

    /**
     * Walks a `let`/`loop` form with progressive binding scope: an init sees
     * the bindings before it, the body sees all of them, and a binding name is
     * never in scope for its own init.
     *
     * @param PersistentListInterface<mixed> $form
     * @param list<Symbol>                   $scope
     */
    private function walkPairwiseForm(PersistentListInterface $form, int $line, int $col, string $word, array $scope): ?Symbol
    {
        $bindingVector = count($form) > 1 ? $form->get(1) : null;
        if (!$bindingVector instanceof PersistentVectorInterface) {
            return $this->walkTail($form, $line, $col, $word, $scope, from: 1);
        }

        $runningScope = $scope;
        $vectorCount = count($bindingVector);

        for ($i = 0; $i + 1 < $vectorCount; $i += 2) {
            $binding = $this->walk($bindingVector->get($i + 1), $line, $col, $word, $runningScope)
                ?? $this->walkPattern($bindingVector->get($i), $line, $col, $word, $runningScope, $runningScope);
            if ($binding instanceof Symbol) {
                return $binding;
            }
        }

        return $this->walkTail($form, $line, $col, $word, $runningScope, from: 2);
    }

    /**
     * Walks `(if-let [pattern test] then else?)` and friends. The `if-` variants
     * expand their `else` outside the binding, so it keeps the outer scope.
     *
     * @param PersistentListInterface<mixed> $form
     * @param list<Symbol>                   $scope
     */
    private function walkSingleBindingForm(PersistentListInterface $form, int $line, int $col, string $word, array $scope): ?Symbol
    {
        $formCount = count($form);
        $bindingVector = $formCount > 1 ? $form->get(1) : null;
        if (!$bindingVector instanceof PersistentVectorInterface || count($bindingVector) !== 2) {
            return $this->walkTail($form, $line, $col, $word, $scope, from: 1);
        }

        $runningScope = $scope;
        $binding = $this->walk($bindingVector->get(1), $line, $col, $word, $scope)
            ?? $this->walkPattern($bindingVector->get(0), $line, $col, $word, $scope, $runningScope);
        if ($binding instanceof Symbol) {
            return $binding;
        }

        $head = $form->get(0);
        $bodyEnd = $head instanceof Symbol
            && in_array($head->getName(), self::ELSE_BRANCH_FORMS, true)
            && $formCount > 3
            ? $formCount - 1
            : $formCount;

        for ($i = 2; $i < $bodyEnd; ++$i) {
            $binding = $this->walk($form->get($i), $line, $col, $word, $runningScope);
            if ($binding instanceof Symbol) {
                return $binding;
            }
        }

        return $this->walkTail($form, $line, $col, $word, $scope, from: $bodyEnd);
    }

    /**
     * Walks `(fn [params ...] body ...)` and `(foreach [k? v coll] body ...)`:
     * a vector of patterns, optionally closed by `$trailingInitCount` ordinary
     * expressions that are evaluated in the outer scope.
     *
     * @param PersistentListInterface<mixed> $form
     * @param list<Symbol>                   $scope
     */
    private function walkParameterForm(PersistentListInterface $form, int $line, int $col, string $word, array $scope, int $trailingInitCount): ?Symbol
    {
        $parameters = count($form) > 1 ? $form->get(1) : null;
        if (!$parameters instanceof PersistentVectorInterface) {
            return $this->walkTail($form, $line, $col, $word, $scope, from: 1);
        }

        $runningScope = $scope;
        $parameterCount = count($parameters);
        $patternCount = $parameterCount - $trailingInitCount;

        for ($i = 0; $i < $parameterCount; ++$i) {
            $binding = $i < $patternCount
                ? $this->walkPattern($parameters->get($i), $line, $col, $word, $scope, $runningScope)
                : $this->walk($parameters->get($i), $line, $col, $word, $scope);
            if ($binding instanceof Symbol) {
                return $binding;
            }
        }

        return $this->walkTail($form, $line, $col, $word, $runningScope, from: 2);
    }

    /**
     * Walks `(catch Type exception body ...)`. The type is an ordinary symbol
     * usage; only the third element binds.
     *
     * @param PersistentListInterface<mixed> $form
     * @param list<Symbol>                   $scope
     */
    private function walkCatchForm(PersistentListInterface $form, int $line, int $col, string $word, array $scope): ?Symbol
    {
        if (count($form) < 3) {
            return $this->walkTail($form, $line, $col, $word, $scope, from: 1);
        }

        $runningScope = $scope;
        $binding = $this->walk($form->get(1), $line, $col, $word, $scope)
            ?? $this->walkPattern($form->get(2), $line, $col, $word, $scope, $runningScope);
        if ($binding instanceof Symbol) {
            return $binding;
        }

        return $this->walkTail($form, $line, $col, $word, $runningScope, from: 3);
    }

    /**
     * @param PersistentListInterface<mixed> $form
     * @param list<Symbol>                   $scope
     */
    private function walkTail(PersistentListInterface $form, int $line, int $col, string $word, array $scope, int $from): ?Symbol
    {
        $formCount = count($form);
        for ($i = $from; $i < $formCount; ++$i) {
            $binding = $this->walk($form->get($i), $line, $col, $word, $scope);
            if ($binding instanceof Symbol) {
                return $binding;
            }
        }

        return null;
    }

    /**
     * Walks a binding pattern, collecting every name it introduces into
     * `$scope` and answering when the cursor sits on one of them, so that
     * navigating from a declaration lands on itself instead of falling through
     * to an unrelated global of the same name.
     *
     * Supports plain symbols and vector/map destructuring. `&` is a rest
     * marker, not a binding. The values of `:or` are default expressions
     * evaluated in the enclosing scope, so they are walked as usages.
     *
     * @param list<Symbol> $outerScope the scope the pattern's own expressions see
     * @param list<Symbol> $scope
     */
    private function walkPattern(mixed $pattern, int $line, int $col, string $word, array $outerScope, array &$scope): ?Symbol
    {
        if ($pattern instanceof Symbol) {
            if ($pattern->getName() === self::REST_MARKER) {
                return null;
            }

            $scope[] = $pattern;

            return $this->isAt($pattern, $line, $col) && $pattern->getName() === $word
                ? $pattern
                : null;
        }

        if ($pattern instanceof PersistentVectorInterface) {
            foreach ($pattern as $child) {
                $binding = $this->walkPattern($child, $line, $col, $word, $outerScope, $scope);
                if ($binding instanceof Symbol) {
                    return $binding;
                }
            }

            return null;
        }

        if ($pattern instanceof PersistentMapInterface) {
            foreach ($pattern as $key => $value) {
                // Mirrors MapBindingDeconstructor: `:or` maps a binding name to
                // a default expression, so its keys name nothing and its values
                // are uses of the enclosing scope, not new bindings.
                $binding = $key instanceof Keyword && $key->getName() === 'or'
                    ? $this->walkOrDefaults($value, $line, $col, $word, $outerScope)
                    : $this->walkPattern($value, $line, $col, $word, $outerScope, $scope);
                if ($binding instanceof Symbol) {
                    return $binding;
                }
            }
        }

        return null;
    }

    /**
     * @param list<Symbol> $outerScope
     */
    private function walkOrDefaults(mixed $defaults, int $line, int $col, string $word, array $outerScope): ?Symbol
    {
        if (!$defaults instanceof PersistentMapInterface) {
            return null;
        }

        foreach ($defaults as $default) {
            $binding = $this->walk($default, $line, $col, $word, $outerScope);
            if ($binding instanceof Symbol) {
                return $binding;
            }
        }

        return null;
    }

    /**
     * Over-approximates the names a {@see self::BARRIER_FORMS} binder
     * introduces: every symbol in a vector it holds directly, plus the leading
     * vector of each arity list, which covers multi-arity `defn`.
     *
     * @param PersistentListInterface<mixed> $form
     */
    private function rebinds(PersistentListInterface $form, string $word): bool
    {
        foreach ($form as $child) {
            if ($child instanceof PersistentListInterface && count($child) > 0) {
                $child = $child->get(0);
            }

            if ($child instanceof PersistentVectorInterface && $this->containsName($child, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param PersistentVectorInterface<mixed> $vector
     */
    private function containsName(PersistentVectorInterface $vector, string $word): bool
    {
        foreach ($vector as $element) {
            if ($element instanceof Symbol && $element->getName() === $word) {
                return true;
            }

            if ($element instanceof PersistentVectorInterface && $this->containsName($element, $word)) {
                return true;
            }

            if ($element instanceof PersistentMapInterface && $this->mapContainsName($element, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $map
     */
    private function mapContainsName(PersistentMapInterface $map, string $word): bool
    {
        foreach ($map as $key => $value) {
            if ($key instanceof Keyword && $key->getName() === 'or') {
                continue;
            }

            if ($value instanceof Symbol && $value->getName() === $word) {
                return true;
            }

            if ($value instanceof PersistentVectorInterface && $this->containsName($value, $word)) {
                return true;
            }

            if ($value instanceof PersistentMapInterface && $this->mapContainsName($value, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Symbol> $scope
     */
    private function lookupScope(array $scope, string $word): ?Symbol
    {
        for ($i = count($scope) - 1; $i >= 0; --$i) {
            if ($scope[$i]->getName() === $word) {
                return $scope[$i];
            }
        }

        return null;
    }

    private function isAt(Symbol $symbol, int $line, int $col): bool
    {
        $start = $symbol->getStartLocation();
        if (!$start instanceof SourceLocation) {
            return false;
        }

        if ($line !== $start->getLine()) {
            return false;
        }

        // The end column is exclusive, but `Document::wordAt()` still reports
        // the word when the caret rests just past its last character, so the
        // comparison has to include it or navigation dies at that caret.
        $cursorCol = $col - 1;

        return $cursorCol >= $start->getColumn() && $cursorCol <= $this->endColumn($symbol, $start);
    }

    /**
     * @param PersistentListInterface<mixed>|PersistentMapInterface<mixed, mixed>|PersistentVectorInterface<mixed> $form
     */
    private function pointInside(PersistentListInterface|PersistentVectorInterface|PersistentMapInterface $form, int $line, int $col): bool
    {
        $start = $form->getStartLocation();
        $end = $form->getEndLocation();
        if (!$start instanceof SourceLocation || !$end instanceof SourceLocation) {
            return true;
        }

        $cursorCol = $col - 1;
        if ($line < $start->getLine() || $line > $end->getLine()) {
            return false;
        }

        if ($line === $start->getLine() && $cursorCol < $start->getColumn()) {
            return false;
        }

        if ($line !== $end->getLine()) {
            return true;
        }

        return $cursorCol <= $end->getColumn();
    }

    private function toLocation(string $uri, Symbol $binding): Location
    {
        $start = $binding->getStartLocation();
        $line = $start?->getLine() ?? 0;
        $column = $start?->getColumn() ?? 0;

        // SourceLocation columns are 0-based; the shared Location contract is
        // 1-based (PositionConverter subtracts one when building LSP ranges).
        return new Location(
            uri: $uri,
            line: $line,
            col: $column + 1,
            endLine: $line,
            endCol: ($start instanceof SourceLocation ? $this->endColumn($binding, $start) : $column) + 1,
        );
    }

    /**
     * The reader always records an end location; the fallback only covers
     * synthetic symbols, and counts codepoints because the lexer's columns do.
     */
    private function endColumn(Symbol $symbol, SourceLocation $start): int
    {
        $end = $symbol->getEndLocation();

        return $end instanceof SourceLocation
            ? $end->getColumn()
            : $start->getColumn() + mb_strlen($symbol->getName());
    }
}
