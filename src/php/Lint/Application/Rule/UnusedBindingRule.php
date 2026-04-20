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
use function str_starts_with;

/**
 * Flags symbols bound in `(let [x ...])` / `(loop [x ...])` whose body
 * never mentions them. Ignores names starting with `_` (idiomatic
 * placeholder) and `&` (variadic marker). Destructuring binding forms
 * are best-effort: only the top-level names are tracked.
 */
final readonly class UnusedBindingRule implements LintRuleInterface
{
    private const array BINDING_FORMS = ['let', 'loop', 'for', 'when-let', 'if-let'];

    public function code(): string
    {
        return RuleRegistry::UNUSED_BINDING;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $result = [];
        foreach ($analysis->forms as $form) {
            $this->walk($form, $analysis->uri, $result);
        }

        return $result;
    }

    /**
     * @param list<Diagnostic> $result
     */
    private function walk(mixed $form, string $uri, array &$result): void
    {
        if ($form instanceof PersistentListInterface && count($form) > 0) {
            $head = $form->get(0);
            if ($head instanceof Symbol && in_array($head->getName(), self::BINDING_FORMS, true)) {
                $this->inspectLet($form, $uri, $result);
            }

            foreach ($form as $child) {
                $this->walk($child, $uri, $result);
            }

            return;
        }

        if ($form instanceof PersistentVectorInterface) {
            foreach ($form as $child) {
                $this->walk($child, $uri, $result);
            }

            return;
        }

        if ($form instanceof PersistentMapInterface) {
            foreach ($form as $k => $v) {
                $this->walk($k, $uri, $result);
                $this->walk($v, $uri, $result);
            }
        }
    }

    /**
     * @param list<Diagnostic> $result
     */
    private function inspectLet(PersistentListInterface $form, string $uri, array &$result): void
    {
        if (count($form) < 2) {
            return;
        }

        $bindings = $form->get(1);
        if (!$bindings instanceof PersistentVectorInterface) {
            return;
        }

        $bindingSyms = [];
        $size = count($bindings);
        for ($i = 0; $i < $size; $i += 2) {
            $sym = $bindings->get($i);
            if ($sym instanceof Symbol && $this->trackable($sym->getName())) {
                $bindingSyms[] = $sym;
            }
        }

        if ($bindingSyms === []) {
            return;
        }

        $usageCounts = [];
        $formSize = count($form);
        for ($i = 2; $i < $formSize; ++$i) {
            $body = $form->get($i);
            FormWalker::walk($body, static function (mixed $val) use (&$usageCounts): void {
                if ($val instanceof Symbol && $val->getNamespace() === null) {
                    $name = $val->getName();
                    $usageCounts[$name] = ($usageCounts[$name] ?? 0) + 1;
                }
            });
        }

        foreach ($bindingSyms as $sym) {
            $name = $sym->getName();
            if (!isset($usageCounts[$name])) {
                $result[] = DiagnosticBuilder::fromForm(
                    $this->code(),
                    sprintf("Unused binding: '%s'.", $name),
                    $uri,
                    $sym,
                );
            }
        }
    }

    private function trackable(string $name): bool
    {
        return $name !== '&' && !str_starts_with($name, '_');
    }
}
