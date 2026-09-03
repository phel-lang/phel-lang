<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;

use function count;
use function sprintf;

/**
 * Flags `(:use Foo\Bar)` or `(:use Foo\Bar :as B)` entries whose imported
 * class alias is never referenced in the file body.
 *
 * @phpstan-type ImportEntry array{alias:string, display:string, anchor: bool|float|int|string|TypeInterface|null}
 *
 * @internal
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
     * @param PersistentListInterface<mixed> $nsForm
     *
     * @return list<ImportEntry>
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
     * @param PersistentListInterface<mixed> $clause
     *
     * @return list<ImportEntry>
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
                'alias' => $alias ?? $this->lastSegmentAlias($item),
                'display' => $item->getName(),
                'anchor' => $item,
            ];
        }

        return $result;
    }

    /**
     * `(:use Foo.Bar.Baz)` and `(:use Foo\Bar\Baz)` both bind the alias `Baz`
     * (`NsSymbol::createAliasFromSymbol`), so both separators have to be split
     * on. Splitting only on `\` reported every dot-separated import as unused.
     */
    private function lastSegmentAlias(Symbol $symbol): string
    {
        return SymbolAlias::lastSegment($symbol->getName());
    }
}
