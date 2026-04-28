<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Api\Transfer\Diagnostic;
use Phel\Compiler\Domain\Exceptions\ErrorCode;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;

use function count;
use function explode;
use function preg_match;

/**
 * Promotes the analyzer's native `PHEL001` undefined-symbol diagnostic
 * into a lint-rule diagnostic so the standard severity/opt-out plumbing
 * applies. Reads from the shared `FileAnalysis::$semanticDiagnostics`
 * cache so the Parser + Analyzer only runs once per file.
 *
 * Alias-qualified symbols (`alias/name`) whose `alias` part is declared
 * via `(:require ... :as alias)` in the file's ns form are suppressed:
 * the linter never evaluates other namespaces, so the analyzer cannot
 * see their definitions even though the call is syntactically valid.
 */
final readonly class UnresolvedSymbolRule implements LintRuleInterface
{
    public function code(): string
    {
        return RuleRegistry::UNRESOLVED_SYMBOL;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $promoted = SemanticDiagnosticPromoter::promote(
            $analysis,
            ErrorCode::UNDEFINED_SYMBOL->value,
            $this->code(),
        );

        if ($promoted === []) {
            return $promoted;
        }

        $aliases = $this->collectRequireAliases($analysis->forms);
        if ($aliases === []) {
            return $promoted;
        }

        $filtered = [];
        foreach ($promoted as $diagnostic) {
            if ($this->isAliasQualified($diagnostic, $aliases)) {
                continue;
            }

            $filtered[] = $diagnostic;
        }

        return $filtered;
    }

    /**
     * @param array<string, true> $aliases
     */
    private function isAliasQualified(Diagnostic $diagnostic, array $aliases): bool
    {
        if (preg_match("/^Cannot resolve symbol '([^']+)'/", $diagnostic->message, $matches) !== 1) {
            return false;
        }

        $parts = explode('/', $matches[1], 2);
        if (count($parts) !== 2 || $parts[0] === '') {
            return false;
        }

        return isset($aliases[$parts[0]]);
    }

    /**
     * @param list<bool|float|int|string|TypeInterface|null> $forms
     *
     * @return array<string, true>
     */
    private function collectRequireAliases(array $forms): array
    {
        $nsForm = NamespaceForm::find($forms);
        if (!$nsForm instanceof PersistentListInterface) {
            return [];
        }

        $aliases = [];
        foreach (NsClauseIterator::clauses($nsForm, 'require') as $clause) {
            foreach ($this->extractAliasesFromClause($clause) as $alias) {
                $aliases[$alias] = true;
            }
        }

        return $aliases;
    }

    /**
     * @return list<string>
     */
    private function extractAliasesFromClause(PersistentListInterface $clause): array
    {
        $aliases = [];
        $size = count($clause);

        for ($i = 1; $i < $size; ++$i) {
            $item = $clause->get($i);

            if ($item instanceof PersistentVectorInterface) {
                $alias = $this->aliasFromVector($item);
                if ($alias !== null) {
                    $aliases[] = $alias;
                }

                continue;
            }

            if ($item instanceof Symbol) {
                $alias = $this->aliasFromFlatClause($clause, $i);
                if ($alias !== null) {
                    $aliases[] = $alias;
                }
            }
        }

        return $aliases;
    }

    private function aliasFromVector(PersistentVectorInterface $vector): ?string
    {
        $size = count($vector);
        for ($i = 1; $i < $size - 1; ++$i) {
            $item = $vector->get($i);
            if (!$item instanceof Keyword) {
                continue;
            }

            if ($item->getName() !== 'as') {
                continue;
            }

            $next = $vector->get($i + 1);
            if ($next instanceof Symbol) {
                return $next->getName();
            }
        }

        return null;
    }

    private function aliasFromFlatClause(PersistentListInterface $clause, int $startIndex): ?string
    {
        $size = count($clause);
        for ($i = $startIndex + 1; $i < $size - 1; ++$i) {
            $item = $clause->get($i);
            if ($item instanceof Symbol || $item instanceof PersistentVectorInterface) {
                return null;
            }

            if (!$item instanceof Keyword) {
                continue;
            }

            if ($item->getName() !== 'as') {
                continue;
            }

            $next = $clause->get($i + 1);
            if ($next instanceof Symbol) {
                return $next->getName();
            }
        }

        return null;
    }
}
