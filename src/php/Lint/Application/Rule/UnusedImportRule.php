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
        $nsForm = NamespaceForm::find($analysis->forms);
        if (!$nsForm instanceof PersistentListInterface) {
            return [];
        }

        $imports = $this->collectImports($nsForm);
        if ($imports === []) {
            return [];
        }

        $used = NamespaceForm::collectSymbolUses($analysis->forms, $nsForm);

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
     * @return list<array{alias:string, display:string, anchor: mixed}>
     */
    private function collectImports(PersistentListInterface $nsForm): array
    {
        $result = [];
        foreach (NsClauseIterator::clauses($nsForm, 'use') as $clause) {
            foreach ($this->importsInClause($clause) as $entry) {
                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * @return list<array{alias:string, display:string, anchor: mixed}>
     */
    private function importsInClause(PersistentListInterface $clause): array
    {
        if (count($clause) < 2) {
            return [];
        }

        $result = [];
        $size = count($clause);
        for ($i = 1; $i < $size; ++$i) {
            $item = $clause->get($i);
            if (!$item instanceof Symbol) {
                continue;
            }

            $alias = null;
            // Look ahead for `:as Alias`.
            if ($i + 2 < $size) {
                $maybeKey = $clause->get($i + 1);
                if ($maybeKey instanceof Keyword && $maybeKey->getName() === 'as') {
                    $aliasCandidate = $clause->get($i + 2);
                    if ($aliasCandidate instanceof Symbol) {
                        $alias = $aliasCandidate->getName();
                        $i += 2;
                    }
                }
            }

            $result[] = [
                'alias' => $alias ?? $this->defaultAlias($item),
                'display' => $item->getName(),
                'anchor' => $item,
            ];
        }

        return $result;
    }

    private function defaultAlias(Symbol $symbol): string
    {
        $name = $symbol->getName();
        $parts = explode('\\', $name);

        return $parts[count($parts) - 1];
    }

}
