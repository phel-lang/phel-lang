<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Api\Transfer\Diagnostic;
use Phel\Compiler\Domain\Exceptions\ErrorCode;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;

use function count;
use function sprintf;

/**
 * Two-track arity check:
 *
 * 1. Promotes any `PHEL002` arity diagnostic the analyzer already raised
 *    (wrong arity on special forms like `if`, `let`, etc.).
 * 2. Scans same-file call-sites against locally-defined `defn`/`defn-`
 *    signatures and flags obvious arity mismatches the analyzer cannot
 *    surface (ordering-dependent, Analyzer sees the call before the def).
 *
 * Cross-namespace arity (via `ProjectIndex`) is deferred to v2 — the
 * v1 index stores formatted signatures, not parsed parameter lists.
 */
final readonly class ArityMismatchRule implements LintRuleInterface
{
    public function code(): string
    {
        return RuleRegistry::ARITY_MISMATCH;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $result = SemanticDiagnosticPromoter::promote(
            $analysis,
            ErrorCode::ARITY_ERROR->value,
            $this->code(),
        );

        $localFns = $this->collectLocalFunctions($analysis->forms);
        if ($localFns === []) {
            return $result;
        }

        foreach ($analysis->forms as $form) {
            $this->inspectCalls($form, $localFns, $analysis->uri, $result);
        }

        return $result;
    }

    /**
     * @param list<mixed> $forms
     *
     * @return array<string, array{min:int, max:int}>
     */
    private function collectLocalFunctions(array $forms): array
    {
        $fns = [];
        foreach ($forms as $form) {
            if (!$form instanceof PersistentListInterface) {
                continue;
            }

            if (count($form) < 3) {
                continue;
            }

            $head = $form->get(0);
            if (!$head instanceof Symbol) {
                continue;
            }

            $name = $head->getName();
            if ($name !== 'defn' && $name !== 'defn-') {
                continue;
            }

            $defName = $form->get(1);
            if (!$defName instanceof Symbol) {
                continue;
            }

            $arities = $this->collectArities($form);
            if ($arities === null) {
                continue;
            }

            $fns[$defName->getName()] = $arities;
        }

        return $fns;
    }

    /**
     * @return ?array{min:int, max:int}
     */
    private function collectArities(PersistentListInterface $form): ?array
    {
        $size = count($form);
        $minArity = PHP_INT_MAX;
        $maxArity = 0;
        $variadic = false;
        $found = false;

        for ($i = 2; $i < $size; ++$i) {
            $child = $form->get($i);

            if ($child instanceof PersistentVectorInterface) {
                [$arity, $isVariadic] = $this->analyseArityVector($child);
                $found = true;
                $minArity = min($minArity, $arity);
                $maxArity = max($maxArity, $arity);
                if ($isVariadic) {
                    $variadic = true;
                }

                break; // single-arity form: first vector is the param list.
            }

            if ($child instanceof PersistentListInterface && count($child) > 0) {
                $head = $child->get(0);
                if ($head instanceof PersistentVectorInterface) {
                    [$arity, $isVariadic] = $this->analyseArityVector($head);
                    $found = true;
                    $minArity = min($minArity, $arity);
                    $maxArity = max($maxArity, $arity);
                    if ($isVariadic) {
                        $variadic = true;
                    }
                }
            }
        }

        if (!$found) {
            return null;
        }

        return [
            'min' => $minArity,
            'max' => $variadic ? PHP_INT_MAX : $maxArity,
        ];
    }

    /**
     * @return array{int, bool}
     */
    private function analyseArityVector(PersistentVectorInterface $params): array
    {
        $count = count($params);
        $variadic = false;

        for ($i = 0; $i < $count; ++$i) {
            $p = $params->get($i);
            if ($p instanceof Symbol && $p->getName() === '&') {
                $variadic = true;
                // Arity excludes the `&` marker and collects all before it as fixed arity.
                $count = $i;

                break;
            }
        }

        return [$count, $variadic];
    }

    /**
     * @param array<string, array{min:int, max:int}> $localFns
     * @param list<Diagnostic>                       $result
     */
    private function inspectCalls(mixed $form, array $localFns, string $uri, array &$result): void
    {
        if ($form instanceof PersistentListInterface && count($form) > 0) {
            $head = $form->get(0);
            if ($head instanceof Symbol && $head->getNamespace() === null) {
                $name = $head->getName();
                if (isset($localFns[$name])) {
                    $given = count($form) - 1;
                    $min = $localFns[$name]['min'];
                    $max = $localFns[$name]['max'];

                    if ($given < $min || $given > $max) {
                        $expected = $max === PHP_INT_MAX ? ($min . '+') : (string) $min;
                        $message = sprintf(
                            "Wrong number of arguments for '%s'. Expected %s, given %d.",
                            $name,
                            $expected,
                            $given,
                        );
                        $result[] = DiagnosticBuilder::fromForm(
                            $this->code(),
                            $message,
                            $uri,
                            $form,
                        );
                    }
                }
            }

            foreach ($form as $child) {
                $this->inspectCalls($child, $localFns, $uri, $result);
            }

            return;
        }

        if ($form instanceof PersistentVectorInterface) {
            foreach ($form as $child) {
                $this->inspectCalls($child, $localFns, $uri, $result);
            }

            return;
        }

        if ($form instanceof PersistentMapInterface) {
            foreach ($form as $k => $v) {
                $this->inspectCalls($k, $localFns, $uri, $result);
                $this->inspectCalls($v, $localFns, $uri, $result);
            }
        }
    }
}
