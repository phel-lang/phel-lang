<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Shared\Binding\IterationHead;

use function count;
use function in_array;
use function sprintf;

/**
 * Flags new `let`/`fn`/`defn`/`for`/`foreach` bindings that shadow a
 * previously-bound local with the same name (outer scope still reachable, easy
 * foot-gun).
 */
final readonly class ShadowedBindingRule implements LintRuleInterface
{
    private const array LET_FORMS = ['let', 'loop', 'if-let', 'when-let'];

    private const array FN_FORMS = ['fn', 'defn', 'defn-', 'defmacro', 'defmacro-'];

    public function code(): string
    {
        return RuleRegistry::SHADOWED_BINDING;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $result = [];
        foreach ($analysis->forms as $form) {
            $this->walk($form, [], $analysis->uri, $result);
        }

        return $result;
    }

    /**
     * @param list<string>     $scope
     * @param list<Diagnostic> $result
     */
    private function walk(mixed $form, array $scope, string $uri, array &$result): void
    {
        if ($form instanceof PersistentListInterface && count($form) > 0) {
            $head = $form->get(0);
            if ($head instanceof Symbol) {
                $name = $head->getName();
                if (in_array($name, self::LET_FORMS, true)) {
                    $scope = $this->handleLet($form, $scope, $uri, $result);
                } elseif (IterationHead::isIterationForm($name)) {
                    $scope = $this->handleIterationHead($name, $form, $scope, $uri, $result);
                } elseif (in_array($name, self::FN_FORMS, true)) {
                    $this->walkFnForm($form, $scope, $uri, $result);

                    return;
                }
            }

            foreach ($form as $child) {
                $this->walk($child, $scope, $uri, $result);
            }

            return;
        }

        if ($form instanceof PersistentVectorInterface) {
            foreach ($form as $child) {
                $this->walk($child, $scope, $uri, $result);
            }

            return;
        }

        if ($form instanceof PersistentMapInterface) {
            foreach ($form as $k => $v) {
                $this->walk($k, $scope, $uri, $result);
                $this->walk($v, $scope, $uri, $result);
            }
        }
    }

    /**
     * @param PersistentListInterface<mixed> $form
     * @param list<string>                   $scope
     * @param list<Diagnostic>               $result
     *
     * @return list<string>
     */
    private function handleLet(PersistentListInterface $form, array $scope, string $uri, array &$result): array
    {
        if (count($form) < 2) {
            return $scope;
        }

        $bindings = $form->get(1);
        if (!$bindings instanceof PersistentVectorInterface) {
            return $scope;
        }

        $size = count($bindings);
        $newScope = $scope;
        for ($i = 0; $i < $size; $i += 2) {
            $newScope = $this->noteBinding($bindings->get($i), $newScope, $uri, $result);
        }

        return $newScope;
    }

    /**
     * `for` / `dofor` / `foreach` heads are triples, tuples and modifiers, not
     * name/value pairs, so only `IterationHead` knows which elements are
     * actually bound names.
     *
     * @param PersistentListInterface<mixed> $form
     * @param list<string>                   $scope
     * @param list<Diagnostic>               $result
     *
     * @return list<string>
     */
    private function handleIterationHead(string $formName, PersistentListInterface $form, array $scope, string $uri, array &$result): array
    {
        if (count($form) < 2) {
            return $scope;
        }

        $head = $form->get(1);
        if (!$head instanceof PersistentVectorInterface) {
            return $scope;
        }

        $newScope = $scope;
        foreach (IterationHead::entries($formName, $head) as $entry) {
            $newScope = $this->noteBinding($entry['binding'], $newScope, $uri, $result);
        }

        return $newScope;
    }

    /**
     * Walks an `fn` / `defn` / `defmacro` form, giving each part the scope it
     * really has:
     *
     * - the header (name, docstring, metadata map) is evaluated *outside* the
     *   function, so an `:inline (fn [x] ...)` does not shadow the `defn`'s
     *   own `x`;
     * - every arity of a multi-arity form starts from the enclosing scope, so
     *   `([coll] ...) ([n coll] ...)` is not read as `coll` shadowing `coll`.
     *
     * @param PersistentListInterface<mixed> $form
     * @param list<string>                   $scope
     * @param list<Diagnostic>               $result
     */
    private function walkFnForm(PersistentListInterface $form, array $scope, string $uri, array &$result): void
    {
        $size = count($form);
        $arityStart = $this->firstArityIndex($form, $size);

        for ($i = 1; $i < $arityStart; ++$i) {
            $this->walk($form->get($i), $scope, $uri, $result);
        }

        if ($arityStart >= $size) {
            return;
        }

        $params = $form->get($arityStart);
        if ($params instanceof PersistentVectorInterface) {
            $bodyScope = $this->walkParams($params, $scope, $uri, $result);
            for ($i = $arityStart + 1; $i < $size; ++$i) {
                $this->walk($form->get($i), $bodyScope, $uri, $result);
            }

            return;
        }

        for ($i = $arityStart; $i < $size; ++$i) {
            $this->walkArity($form->get($i), $scope, $uri, $result);
        }
    }

    /**
     * @param list<string>     $scope
     * @param list<Diagnostic> $result
     */
    private function walkArity(mixed $arity, array $scope, string $uri, array &$result): void
    {
        if (!$arity instanceof PersistentListInterface || count($arity) === 0) {
            $this->walk($arity, $scope, $uri, $result);

            return;
        }

        $params = $arity->get(0);
        if (!$params instanceof PersistentVectorInterface) {
            $this->walk($arity, $scope, $uri, $result);

            return;
        }

        $bodyScope = $this->walkParams($params, $scope, $uri, $result);
        $size = count($arity);
        for ($i = 1; $i < $size; ++$i) {
            $this->walk($arity->get($i), $bodyScope, $uri, $result);
        }
    }

    /**
     * Index of the first child that starts the callable part: a params vector
     * (single arity) or the first `([params] body)` list (multi arity).
     *
     * @param PersistentListInterface<mixed> $form
     */
    private function firstArityIndex(PersistentListInterface $form, int $size): int
    {
        for ($i = 1; $i < $size; ++$i) {
            $child = $form->get($i);
            if ($child instanceof PersistentVectorInterface) {
                return $i;
            }

            if ($child instanceof PersistentListInterface
                && count($child) > 0
                && $child->get(0) instanceof PersistentVectorInterface
            ) {
                return $i;
            }
        }

        return $size;
    }

    /**
     * @param PersistentVectorInterface<mixed> $params
     * @param list<string>                     $scope
     * @param list<Diagnostic>                 $result
     *
     * @return list<string>
     */
    private function walkParams(PersistentVectorInterface $params, array $scope, string $uri, array &$result): array
    {
        $newScope = $scope;
        $count = count($params);
        for ($i = 0; $i < $count; ++$i) {
            $newScope = $this->noteBinding($params->get($i), $newScope, $uri, $result);
        }

        return $newScope;
    }

    /**
     * Adds one bound name to `$scope`, reporting it first when the same name is
     * already in scope. `&` and `_` are ignored: the former is the variadic
     * marker, the latter the conventional "unused" placeholder.
     *
     * @param list<string>     $scope
     * @param list<Diagnostic> $result
     *
     * @return list<string>
     */
    private function noteBinding(mixed $sym, array $scope, string $uri, array &$result): array
    {
        if (!$sym instanceof Symbol) {
            return $scope;
        }

        $name = $sym->getName();
        if ($name === '&' || $name === '_') {
            return $scope;
        }

        if (in_array($name, $scope, true)) {
            $result[] = DiagnosticBuilder::fromForm(
                $this->code(),
                sprintf("Shadowed binding: '%s' shadows a local with the same name.", $name),
                $uri,
                $sym,
            );
        }

        $scope[] = $name;

        return $scope;
    }
}
