<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Shared\Api\Diagnostic;

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
    private const array LET_LIKE_FORMS = ['let', 'loop', 'if-let', 'when-let', 'binding'];

    private const array FN_FORMS = ['fn', 'defn', 'defn-', 'defmacro', 'defmacro-'];

    /**
     * `for`/`dofor` do NOT take name/value pairs. Their head is a sequence of
     * `binding :verb expr` triples interleaved with `:modifier arg` pairs, so
     * an odd element count is the normal case, not an error.
     */
    private const array FOR_FORMS = ['for', 'dofor'];

    public function code(): string
    {
        return RuleRegistry::INVALID_DESTRUCTURING;
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

                $name = $head->getName();
                if (in_array($name, self::LET_LIKE_FORMS, true)) {
                    $this->inspectLet($node, $analysis->uri, $result);
                } elseif (in_array($name, self::FOR_FORMS, true)) {
                    $this->inspectFor($node, $analysis->uri, $result);
                } elseif (in_array($name, self::FN_FORMS, true)) {
                    $this->inspectFn($node, $analysis->uri, $result);
                }
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
     * Validates a `for`/`dofor` head against its own grammar: a keyword
     * starts a `:modifier arg` (or `:reduce [...]` option) pair, anything
     * else starts a `binding :verb expr` triple. A head that runs out of
     * forms mid-triple is the actual error worth reporting.
     *
     * @param PersistentListInterface<mixed> $form
     * @param list<Diagnostic>               $result
     */
    private function inspectFor(PersistentListInterface $form, string $uri, array &$result): void
    {
        if (count($form) < 2) {
            return;
        }

        $head = $form->get(1);
        if (!$head instanceof PersistentVectorInterface) {
            $result[] = DiagnosticBuilder::fromForm(
                $this->code(),
                'Binding form expects a vector of name/value pairs.',
                $uri,
                $form,
            );

            return;
        }

        $count = count($head);
        $i = 0;
        while ($i < $count) {
            $step = $head->get($i) instanceof Keyword ? 2 : 3;
            if ($i + $step > $count) {
                $result[] = DiagnosticBuilder::fromForm(
                    $this->code(),
                    $step === 2
                        ? 'Incomplete `for` head: a modifier keyword needs an argument.'
                        : 'Incomplete `for` head: a binding needs a `:verb` and an expression.',
                    $uri,
                    $head,
                );

                return;
            }

            $i += $step;
        }
    }

    /**
     * @param PersistentListInterface<mixed> $form
     * @param list<Diagnostic>               $result
     */
    private function inspectFn(PersistentListInterface $form, string $uri, array &$result): void
    {
        foreach (FnParamVectors::of($form) as $paramVector) {
            $this->validateParamVector($paramVector, $uri, $result);
        }
    }

    /**
     * @param PersistentVectorInterface<mixed> $params
     * @param list<Diagnostic>                 $result
     */
    private function validateParamVector(PersistentVectorInterface $params, string $uri, array &$result): void
    {
        $count = count($params);
        for ($i = 0; $i < $count; ++$i) {
            $item = $params->get($i);
            if ($item instanceof Symbol && $item->getName() === '&') {
                // A bare trailing `&` is legal: it swallows the rest without
                // binding it (`(defmacro comment [&])` in phel\core).
                $remaining = $count - $i - 1;
                if ($remaining > 1) {
                    $result[] = DiagnosticBuilder::fromForm(
                        $this->code(),
                        "Invalid destructuring: '&' must be followed by at most one binding symbol.",
                        $uri,
                        $item,
                    );
                }

                break;
            }
        }
    }
}
