<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;

use function count;
use function explode;
use function sprintf;

/**
 * Flags `(:use Foo\Bar)` or `(:use Foo\Bar :as B)` entries whose imported
 * class alias is never referenced in the file body.
 */
final readonly class UnusedImportRule implements LintRuleInterface
{
    public function code(): string
    {
        return RuleRegistry::UNUSED_IMPORT;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $nsForm = $this->findNsForm($analysis->forms);
        if (!$nsForm instanceof PersistentListInterface) {
            return [];
        }

        $imports = $this->collectImports($nsForm);
        if ($imports === []) {
            return [];
        }

        $used = $this->collectSymbolUses($analysis->forms, $nsForm);

        $result = [];
        foreach ($imports as $entry) {
            if (!isset($used[$entry['alias']])) {
                $result[] = DiagnosticBuilder::fromForm(
                    $this->code(),
                    sprintf("Unused import: '%s'.", $entry['display']),
                    $analysis->uri,
                    $entry['anchor'],
                );
            }
        }

        return $result;
    }

    /**
     * @param list<mixed> $forms
     */
    private function findNsForm(array $forms): ?PersistentListInterface
    {
        foreach ($forms as $form) {
            if (!$form instanceof PersistentListInterface) {
                continue;
            }

            if (count($form) === 0) {
                continue;
            }

            $head = $form->get(0);
            if ($head instanceof Symbol && $head->getName() === Symbol::NAME_NS) {
                return $form;
            }
        }

        return null;
    }

    /**
     * @return list<array{alias:string, display:string, anchor: mixed}>
     */
    private function collectImports(PersistentListInterface $nsForm): array
    {
        $result = [];
        $size = count($nsForm);

        for ($i = 2; $i < $size; ++$i) {
            $child = $nsForm->get($i);
            if (!$child instanceof PersistentListInterface) {
                continue;
            }

            if (count($child) < 2) {
                continue;
            }

            $head = $child->get(0);
            if (!$head instanceof Keyword) {
                continue;
            }

            if ($head->getName() !== 'use') {
                continue;
            }

            $inner = count($child);
            for ($j = 1; $j < $inner; ++$j) {
                $item = $child->get($j);
                if (!$item instanceof Symbol) {
                    continue;
                }

                $target = $item;
                $alias = null;

                // Look ahead for `:as Alias`.
                if ($j + 1 < $inner) {
                    $maybeKey = $child->get($j + 1);
                    if ($maybeKey instanceof Keyword && $maybeKey->getName() === 'as' && $j + 2 < $inner) {
                        $aliasCandidate = $child->get($j + 2);
                        if ($aliasCandidate instanceof Symbol) {
                            $alias = $aliasCandidate->getName();
                            $j += 2;
                        }
                    }
                }

                $result[] = [
                    'alias' => $alias ?? $this->defaultAlias($target),
                    'display' => $target->getName(),
                    'anchor' => $target,
                ];
            }
        }

        return $result;
    }

    private function defaultAlias(Symbol $symbol): string
    {
        $name = $symbol->getName();
        $parts = explode('\\', $name);

        return $parts[count($parts) - 1];
    }

    /**
     * @param list<mixed> $forms
     *
     * @return array<string, true>
     */
    private function collectSymbolUses(array $forms, PersistentListInterface $nsForm): array
    {
        $seen = [];
        $visit = static function (mixed $value) use (&$seen): void {
            if ($value instanceof Symbol) {
                $seen[$value->getName()] = true;
                $ns = $value->getNamespace();
                if ($ns !== null && $ns !== '') {
                    $seen[$ns] = true;
                }
            }
        };

        foreach ($forms as $form) {
            if ($form === $nsForm) {
                continue;
            }

            FormWalker::walk($form, static function (mixed $value) use ($visit): void {
                $visit($value);
            });
        }

        return $seen;
    }
}
