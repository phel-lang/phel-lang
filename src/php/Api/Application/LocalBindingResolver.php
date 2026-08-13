<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Api\Location;
use Phel\Shared\Facade\CompilerFacadeInterface;

use Throwable;

use function count;
use function in_array;
use function strlen;

/**
 * Resolves the lexical binding a cursor sits on to the symbol that introduced
 * it inside a `let` (or `loop`) binding vector.
 *
 * Mirrors the scope walk of {@see PointCompleter}, but instead of collecting
 * every name in scope it keeps the binding {@see Symbol} (which carries its
 * source location) and only answers when the cursor is exactly on a usage of
 * one of those names. The walk is lexical and best-effort: an unparseable
 * buffer contributes nothing, and a `Throwable` mid-walk degrades to "not a
 * local binding" rather than failing the navigation request.
 *
 * @internal
 */
final readonly class LocalBindingResolver
{
    private const array BINDING_FORMS = [
        Symbol::NAME_LET,
        Symbol::NAME_LOOP,
    ];

    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    /**
     * @param int $line 1-based line of the cursor
     * @param int $col  1-based column of the cursor
     */
    public function resolve(string $source, string $uri, int $line, int $col, string $word): ?Location
    {
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
            if (!$this->pointInside($form, $line, $col)) {
                return null;
            }

            $head = count($form) > 0 ? $form->get(0) : null;
            if ($head instanceof Symbol && in_array($head->getName(), self::BINDING_FORMS, true)) {
                return $this->walkBindingForm($form, $line, $col, $word, $scope);
            }

            foreach ($form as $child) {
                $binding = $this->walk($child, $line, $col, $word, $scope);
                if ($binding instanceof Symbol) {
                    return $binding;
                }
            }

            return null;
        }

        if ($form instanceof PersistentVectorInterface) {
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
                $binding = $this->walk($key, $line, $col, $word, $scope);
                if ($binding instanceof Symbol) {
                    return $binding;
                }

                $binding = $this->walk($value, $line, $col, $word, $scope);
                if ($binding instanceof Symbol) {
                    return $binding;
                }
            }

            return null;
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
    private function walkBindingForm(PersistentListInterface $form, int $line, int $col, string $word, array $scope): ?Symbol
    {
        if (count($form) < 2) {
            return null;
        }

        $bindingVector = $form->get(1);
        if (!$bindingVector instanceof PersistentVectorInterface) {
            // Malformed bindings: degrade to walking the tail as ordinary forms.
            foreach ($form as $index => $child) {
                if ($index === 0) {
                    continue;
                }

                $binding = $this->walk($child, $line, $col, $word, $scope);
                if ($binding instanceof Symbol) {
                    return $binding;
                }
            }

            return null;
        }

        $runningScope = $scope;
        $vectorCount = count($bindingVector);

        for ($i = 0; $i + 1 < $vectorCount; $i += 2) {
            $binding = $this->walk($bindingVector->get($i + 1), $line, $col, $word, $runningScope);
            if ($binding instanceof Symbol) {
                return $binding;
            }

            $this->addBinding($bindingVector->get($i), $runningScope);
        }

        $formCount = count($form);
        for ($i = 2; $i < $formCount; ++$i) {
            $binding = $this->walk($form->get($i), $line, $col, $word, $runningScope);
            if ($binding instanceof Symbol) {
                return $binding;
            }
        }

        return null;
    }

    /**
     * Adds every name a binding pattern introduces. Supports plain symbols and
     * vector/map destructuring; `&` and `.` are markers, not bindings.
     *
     * @param list<Symbol> $scope
     */
    private function addBinding(mixed $binding, array &$scope): void
    {
        if ($binding instanceof Symbol) {
            if (!in_array($binding->getName(), ['&', '.'], true)) {
                $scope[] = $binding;
            }

            return;
        }

        if ($binding instanceof PersistentVectorInterface) {
            foreach ($binding as $child) {
                $this->addBinding($child, $scope);
            }

            return;
        }

        if ($binding instanceof PersistentMapInterface) {
            foreach ($binding as $value) {
                $this->addBinding($value, $scope);
            }
        }
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

        $cursorCol = $col - 1;
        $end = $symbol->getEndLocation();
        $endCol = $end instanceof SourceLocation
            ? $end->getColumn()
            : $start->getColumn() + strlen($symbol->getName());

        return $cursorCol >= $start->getColumn() && $cursorCol < $endCol;
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
            endCol: $column + 1 + strlen($binding->getName()),
        );
    }
}
