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

use function count;
use function in_array;
use function sprintf;

/**
 * Flags new `let`/`fn`/`defn` bindings that shadow a previously-bound
 * local with the same name (outer scope still reachable, easy foot-gun).
 */
final readonly class ShadowedBindingRule implements LintRuleInterface
{
    private const array LET_FORMS = ['let', 'loop', 'for', 'if-let', 'when-let'];

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
                } elseif (in_array($name, self::FN_FORMS, true)) {
                    $scope = $this->handleFn($form, $scope, $uri, $result);
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
     * @param list<string>     $scope
     * @param list<Diagnostic> $result
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
            $sym = $bindings->get($i);
            if (!$sym instanceof Symbol) {
                continue;
            }

            $name = $sym->getName();
            if ($name === '&') {
                continue;
            }

            if ($name === '_') {
                continue;
            }

            if (in_array($name, $newScope, true)) {
                $result[] = DiagnosticBuilder::fromForm(
                    $this->code(),
                    sprintf("Shadowed binding: '%s' shadows a local with the same name.", $name),
                    $uri,
                    $sym,
                );
            }

            $newScope[] = $name;
        }

        return $newScope;
    }

    /**
     * @param list<string>     $scope
     * @param list<Diagnostic> $result
     *
     * @return list<string>
     */
    private function handleFn(PersistentListInterface $form, array $scope, string $uri, array &$result): array
    {
        $newScope = $scope;
        $first = true;
        foreach (FnParamVectors::of($form) as $paramVector) {
            if ($first) {
                $newScope = $this->walkParams($paramVector, $newScope, $uri, $result);
                $first = false;
                continue;
            }

            // Each arity introduces its own scope extension at analyze-time;
            // still flag inner shadowing of the outer scope.
            $this->walkParams($paramVector, $newScope, $uri, $result);
        }

        return $newScope;
    }

    /**
     * @param list<string>     $scope
     * @param list<Diagnostic> $result
     *
     * @return list<string>
     */
    private function walkParams(PersistentVectorInterface $params, array $scope, string $uri, array &$result): array
    {
        $newScope = $scope;
        $count = count($params);
        for ($i = 0; $i < $count; ++$i) {
            $sym = $params->get($i);
            if (!$sym instanceof Symbol) {
                continue;
            }

            $name = $sym->getName();
            if ($name === '&') {
                continue;
            }

            if ($name === '_') {
                continue;
            }

            if (in_array($name, $newScope, true)) {
                $result[] = DiagnosticBuilder::fromForm(
                    $this->code(),
                    sprintf("Shadowed binding: '%s' shadows a local with the same name.", $name),
                    $uri,
                    $sym,
                );
            }

            $newScope[] = $name;
        }

        return $newScope;
    }
}
