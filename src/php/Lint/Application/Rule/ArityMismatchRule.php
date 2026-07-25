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
use Phel\Shared\Exceptions\ErrorCode;

use function count;
use function in_array;
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
 *
 * @phpstan-type ArityRange array{min:int, max:int}
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
     * @return array<string, ArityRange>
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
     * @param PersistentListInterface<mixed> $form
     *
     * @return ?ArityRange
     */
    private function collectArities(PersistentListInterface $form): ?array
    {
        $minArity = PHP_INT_MAX;
        $maxArity = 0;
        $variadic = false;
        $found = false;

        foreach (FnParamVectors::of($form) as $params) {
            [$arity, $isVariadic] = $this->analyseArityVector($params);
            $found = true;
            $minArity = min($minArity, $arity);
            $maxArity = max($maxArity, $arity);
            if ($isVariadic) {
                $variadic = true;
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
     * @param PersistentVectorInterface<mixed> $params
     *
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
     * @param array<string, ArityRange> $localFns
     * @param list<Diagnostic>          $result
     */
    private function inspectCalls(mixed $form, array $localFns, string $uri, array &$result): void
    {
        if ($form instanceof PersistentListInterface && count($form) > 0) {
            $head = $form->get(0);
            if ($head instanceof Symbol && $this->hasImplicitArgumentSegments($head)) {
                $this->inspectSegmentedForm($form, $localFns, $uri, $result);

                return;
            }

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

    /**
     * Forms whose nested `(name args...)` segments are NOT complete calls:
     *
     * - `(php/-> obj (method a))` / `(php/:: Cls (method a))` name a PHP
     *   method, never a Phel fn;
     * - threading macros splice an extra argument into each segment, so the
     *   literal argument count is always one short of the real one.
     *
     * Counting those segments as plain calls produces pure noise, so their
     * heads are skipped and only their arguments are inspected.
     */
    private function hasImplicitArgumentSegments(Symbol $head): bool
    {
        return in_array(
            $head->getFullName(),
            [
                Symbol::NAME_PHP_OBJECT_CALL,
                Symbol::NAME_PHP_OBJECT_STATIC_CALL,
                '->',
                '->>',
                'some->',
                'some->>',
                'cond->',
                'cond->>',
                'as->',
                'doto',
            ],
            true,
        );
    }

    /**
     * Descends into the first operand and into each segment's *arguments*,
     * skipping the head of every segment.
     *
     * @param PersistentListInterface<mixed> $form
     * @param array<string, ArityRange>      $localFns
     * @param list<Diagnostic>               $result
     */
    private function inspectSegmentedForm(
        PersistentListInterface $form,
        array $localFns,
        string $uri,
        array &$result,
    ): void {
        $count = count($form);
        for ($i = 1; $i < $count; ++$i) {
            $segment = $form->get($i);

            // Index 1 is the receiver / threaded value: an ordinary
            // expression. Every later segment is either a `(head args...)`
            // list or a bare symbol.
            if ($i === 1 || !$segment instanceof PersistentListInterface) {
                $this->inspectCalls($segment, $localFns, $uri, $result);
                continue;
            }

            $segmentCount = count($segment);
            for ($j = 1; $j < $segmentCount; ++$j) {
                $this->inspectCalls($segment->get($j), $localFns, $uri, $result);
            }
        }
    }
}
