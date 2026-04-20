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

/**
 * Structural checks on binding forms:
 *
 * - `(let bindings ...)` where the binding vector has an odd element count
 *   (one value missing its name).
 * - Variadic marker `&` appearing in an invalid position (anything but
 *   exactly one-symbol-before-the-end).
 */
final readonly class InvalidDestructuringRule implements LintRuleInterface
{
    private const array LET_LIKE_FORMS = ['let', 'loop', 'for', 'if-let', 'when-let', 'binding'];

    private const array FN_FORMS = ['fn', 'defn', 'defn-', 'defmacro', 'defmacro-'];

    public function code(): string
    {
        return RuleRegistry::INVALID_DESTRUCTURING;
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
            if ($head instanceof Symbol) {
                $name = $head->getName();
                if (in_array($name, self::LET_LIKE_FORMS, true)) {
                    $this->inspectLet($form, $uri, $result);
                } elseif (in_array($name, self::FN_FORMS, true)) {
                    $this->inspectFn($form, $uri, $result);
                }
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
            $result[] = DiagnosticBuilder::fromForm(
                $this->code(),
                'Binding form expects a vector of name/value pairs.',
                $uri,
                $form,
            );

            return;
        }

        if (count($bindings) % 2 !== 0) {
            $result[] = DiagnosticBuilder::fromForm(
                $this->code(),
                'Binding vector has an odd number of forms; every name must be paired with a value.',
                $uri,
                $bindings,
            );
        }
    }

    /**
     * @param list<Diagnostic> $result
     */
    private function inspectFn(PersistentListInterface $form, string $uri, array &$result): void
    {
        $size = count($form);
        for ($i = 1; $i < $size; ++$i) {
            $child = $form->get($i);
            if ($child instanceof PersistentVectorInterface) {
                $this->validateParamVector($child, $uri, $result);

                break;
            }

            if ($child instanceof PersistentListInterface && count($child) > 0) {
                $head = $child->get(0);
                if ($head instanceof PersistentVectorInterface) {
                    $this->validateParamVector($head, $uri, $result);
                }
            }
        }
    }

    /**
     * @param list<Diagnostic> $result
     */
    private function validateParamVector(PersistentVectorInterface $params, string $uri, array &$result): void
    {
        $count = count($params);
        for ($i = 0; $i < $count; ++$i) {
            $item = $params->get($i);
            if ($item instanceof Symbol && $item->getName() === '&') {
                $remaining = $count - $i - 1;
                if ($remaining !== 1) {
                    $result[] = DiagnosticBuilder::fromForm(
                        $this->code(),
                        "Invalid destructuring: '&' must be followed by exactly one binding symbol.",
                        $uri,
                        $item,
                    );
                }

                break;
            }
        }
    }
}
