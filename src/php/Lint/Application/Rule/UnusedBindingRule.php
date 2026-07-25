<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Binding\IterationHead;

use function count;
use function in_array;
use function sprintf;
use function str_starts_with;

/**
 * Flags symbols bound in `(let [x ...])` / `(loop [x ...])` /
 * `(for [x :in ...])` / `(foreach [x ...])` whose body never mentions them.
 * Ignores names starting with `_` (idiomatic placeholder) and `&` (variadic
 * marker). Destructuring binding forms are best-effort: only the top-level
 * names are tracked.
 */
final readonly class UnusedBindingRule implements LintRuleInterface
{
    private const array BINDING_FORMS = ['let', 'loop', 'when-let', 'if-let'];

    public function code(): string
    {
        return RuleRegistry::UNUSED_BINDING;
    }

    public function apply(FileAnalysis $analysis): array
    {
        /** @var list<Diagnostic> $result */
        $result = [];
        foreach ($analysis->forms as $form) {
            FormWalker::walk($form, function (mixed $node) use ($analysis, &$result): void {
                if (!$node instanceof PersistentListInterface || count($node) === 0) {
                    return;
                }

                $head = $node->get(0);
                if (!$head instanceof Symbol) {
                    return;
                }

                if (IterationHead::isIterationForm($head->getName())) {
                    $this->inspectIterationHead($head->getName(), $node, $analysis->uri, $result);

                    return;
                }

                if (!in_array($head->getName(), self::BINDING_FORMS, true)) {
                    return;
                }

                $this->inspectLet($node, $analysis->uri, $result);
            });
        }

        return $result;
    }

    /**
     * `for` / `dofor` heads mix binding triples, modifiers and options, and a
     * `foreach` head ends in the collection, so the bound names and the forms
     * that may legitimately reference them both come from `IterationHead`
     * instead of a pairwise scan.
     *
     * @param PersistentListInterface<mixed> $form
     * @param list<Diagnostic>               $result
     */
    private function inspectIterationHead(string $formName, PersistentListInterface $form, string $uri, array &$result): void
    {
        if (count($form) < 2) {
            return;
        }

        $head = $form->get(1);
        if (!$head instanceof PersistentVectorInterface) {
            return;
        }

        $entries = IterationHead::entries($formName, $head);
        if ($entries === []) {
            return;
        }

        $bodyUsageCounts = $this->countSymbolUses($this->bodyForms($form));

        foreach ($entries as $entry) {
            $sym = $entry['binding'];
            if (!$sym instanceof Symbol) {
                continue;
            }

            if (!$this->trackable($sym->getName())) {
                continue;
            }

            $name = $sym->getName();
            if (isset($bodyUsageCounts[$name])) {
                continue;
            }

            $headUsageCounts = $this->countSymbolUses($entry['usageForms']);
            if (isset($headUsageCounts[$name])) {
                continue;
            }

            $result[] = DiagnosticBuilder::fromForm(
                $this->code(),
                sprintf("Unused binding: '%s'.", $name),
                $uri,
                $sym,
            );
        }
    }

    /**
     * @param PersistentListInterface<mixed> $form
     *
     * @return list<mixed>
     */
    private function bodyForms(PersistentListInterface $form): array
    {
        $body = [];
        $size = count($form);
        for ($i = 2; $i < $size; ++$i) {
            $body[] = $form->get($i);
        }

        return $body;
    }

    /**
     * @param list<mixed> $forms
     *
     * @return array<string, int>
     */
    private function countSymbolUses(array $forms): array
    {
        $counts = [];
        foreach ($forms as $form) {
            FormWalker::walk($form, static function (mixed $val) use (&$counts): void {
                if ($val instanceof Symbol && $val->getNamespace() === null) {
                    $name = $val->getName();
                    $counts[$name] = ($counts[$name] ?? 0) + 1;
                }
            });
        }

        return $counts;
    }

    /**
     * @param PersistentListInterface<mixed> $form
     * @param list<Diagnostic>               $result
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

        /** @var list<array{idx: int, sym: Symbol}> $bindingPairs */
        $bindingPairs = [];
        $size = count($bindings);
        for ($i = 0; $i < $size; $i += 2) {
            $sym = $bindings->get($i);
            if ($sym instanceof Symbol && $this->trackable($sym->getName())) {
                $bindingPairs[] = ['idx' => $i, 'sym' => $sym];
            }
        }

        if ($bindingPairs === []) {
            return;
        }

        $bodyUsageCounts = $this->countSymbolUses($this->bodyForms($form));

        foreach ($bindingPairs as $pair) {
            $name = $pair['sym']->getName();
            if (isset($bodyUsageCounts[$name])) {
                continue;
            }

            if ($this->referencedInLaterBindingValues($bindings, $pair['idx'], $name, $size)) {
                continue;
            }

            $result[] = DiagnosticBuilder::fromForm(
                $this->code(),
                sprintf("Unused binding: '%s'.", $name),
                $uri,
                $pair['sym'],
            );
        }
    }

    /**
     * @param PersistentVectorInterface<mixed> $bindings
     */
    private function referencedInLaterBindingValues(
        PersistentVectorInterface $bindings,
        int $nameIdx,
        string $name,
        int $size,
    ): bool {
        $found = false;
        for ($j = $nameIdx + 3; $j < $size; $j += 2) {
            $value = $bindings->get($j);
            FormWalker::walk($value, static function (mixed $val) use ($name, &$found): void {
                if ($found) {
                    return;
                }

                if ($val instanceof Symbol && $val->getNamespace() === null && $val->getName() === $name) {
                    $found = true;
                }
            });
            if ($found) {
                return true;
            }
        }

        return false;
    }

    private function trackable(string $name): bool
    {
        return $name !== '&' && !str_starts_with($name, '_');
    }
}
