<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
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
        /** @var list<Diagnostic> $result */
        $result = [];
        foreach ($analysis->forms as $form) {
            FormWalker::walk($form, function (mixed $node) use ($analysis, &$result): void {
                if (!$node instanceof PersistentListInterface || count($node) === 0) {
                    return;
                }

                $head = $node->get(0);
                if (!$head instanceof Symbol || !in_array($head->getName(), self::BINDING_FORMS, true)) {
                    return;
                }

                $this->inspectLet($node, $analysis->uri, $result);
            });
        }

        return $result;
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

        $bodyUsageCounts = [];
        $formSize = count($form);
        for ($i = 2; $i < $formSize; ++$i) {
            $body = $form->get($i);
            FormWalker::walk($body, static function (mixed $val) use (&$bodyUsageCounts): void {
                if ($val instanceof Symbol && $val->getNamespace() === null) {
                    $name = $val->getName();
                    $bodyUsageCounts[$name] = ($bodyUsageCounts[$name] ?? 0) + 1;
                }
            });
        }

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
