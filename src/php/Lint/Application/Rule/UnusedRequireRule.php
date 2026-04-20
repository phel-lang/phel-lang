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

use function count;
use function sprintf;

/**
 * Flags `(:require foo)` or `(:require foo :refer [bar])` entries whose
 * alias and referred symbols are never mentioned anywhere else in the file.
 */
final readonly class UnusedRequireRule implements LintRuleInterface
{
    public function code(): string
    {
        return RuleRegistry::UNUSED_REQUIRE;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $nsForm = NamespaceForm::find($analysis->forms);
        if (!$nsForm instanceof PersistentListInterface) {
            return [];
        }

        $requires = $this->collectRequires($nsForm);
        if ($requires === []) {
            return [];
        }

        $usedSymbols = NamespaceForm::collectSymbolUses($analysis->forms, $nsForm);

        $result = [];
        foreach ($requires as $entry) {
            $alias = $entry['alias'];
            $refers = $entry['refers'];
            $anchor = $entry['anchor'];

            $aliasUsed = $alias !== '' && $this->symbolUsed($alias, $usedSymbols);
            $referUsed = false;

            foreach ($refers as $refer) {
                if ($this->symbolUsed($refer, $usedSymbols)) {
                    $referUsed = true;

                    break;
                }
            }

            if (!$aliasUsed && !$referUsed && $refers === []) {
                $result[] = DiagnosticBuilder::fromForm(
                    $this->code(),
                    sprintf("Unused require: '%s'.", $entry['display']),
                    $analysis->uri,
                    $anchor,
                );
            } elseif (!$aliasUsed && $refers !== [] && !$referUsed) {
                $result[] = DiagnosticBuilder::fromForm(
                    $this->code(),
                    sprintf("Unused require: '%s' (no referred symbols or alias are used).", $entry['display']),
                    $analysis->uri,
                    $anchor,
                );
            }
        }

        return $result;
    }

    /**
     * @return list<array{alias:string, refers:list<string>, display:string, anchor: mixed}>
     */
    private function collectRequires(PersistentListInterface $nsForm): array
    {
        $result = [];
        foreach (NsClauseIterator::clauses($nsForm, 'require') as $clause) {
            foreach ($this->parseRequireClauseEntries($clause) as $entry) {
                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * @return list<array{alias:string, refers:list<string>, display:string, anchor: mixed}>
     */
    private function parseRequireClauseEntries(PersistentListInterface $clause): array
    {
        $entries = [];
        $size = count($clause);

        for ($i = 1; $i < $size; ++$i) {
            $item = $clause->get($i);

            if ($item instanceof PersistentVectorInterface) {
                $entries[] = $this->parseVectorEntry($item);

                continue;
            }

            if ($item instanceof Symbol) {
                [$entry, $advance] = $this->parseFlatEntry($clause, $i);
                $entries[] = $entry;
                $i = $advance - 1;
            }
        }

        return $entries;
    }

    /**
     * @return array{alias:string, refers:list<string>, display:string, anchor: mixed}
     */
    private function parseVectorEntry(PersistentVectorInterface $vector): array
    {
        $target = null;
        $alias = null;
        $refers = [];

        $count = count($vector);
        for ($i = 0; $i < $count; ++$i) {
            $item = $vector->get($i);

            if ($i === 0 && $item instanceof Symbol) {
                $target = $item;

                continue;
            }

            if (!$item instanceof Keyword) {
                continue;
            }

            $next = $i + 1 < $count ? $vector->get($i + 1) : null;
            if ($item->getName() === 'as' && $next instanceof Symbol) {
                $alias = $next->getName();
                ++$i;
            } elseif ($item->getName() === 'refer' && $next instanceof PersistentVectorInterface) {
                foreach ($next as $r) {
                    if ($r instanceof Symbol) {
                        $refers[] = $r->getName();
                    }
                }

                ++$i;
            }
        }

        return [
            'alias' => $alias ?? $this->defaultAlias($target),
            'refers' => $refers,
            'display' => $target instanceof Symbol ? $target->getName() : 'unknown',
            'anchor' => $vector,
        ];
    }

    /**
     * @return array{0: array{alias:string, refers:list<string>, display:string, anchor: mixed}, 1: int}
     */
    private function parseFlatEntry(PersistentListInterface $clause, int $startIndex): array
    {
        $size = count($clause);
        $target = $clause->get($startIndex);
        $alias = null;
        $refers = [];
        $i = $startIndex + 1;

        while ($i < $size) {
            $item = $clause->get($i);
            if ($item instanceof Symbol || $item instanceof PersistentVectorInterface) {
                break;
            }

            if (!$item instanceof Keyword) {
                break;
            }

            $next = $i + 1 < $size ? $clause->get($i + 1) : null;
            if ($item->getName() === 'as' && $next instanceof Symbol) {
                $alias = $next->getName();
                $i += 2;

                continue;
            }

            if ($item->getName() === 'refer' && $next instanceof PersistentVectorInterface) {
                foreach ($next as $r) {
                    if ($r instanceof Symbol) {
                        $refers[] = $r->getName();
                    }
                }

                $i += 2;

                continue;
            }

            $i += 2;
        }

        return [[
            'alias' => $alias ?? $this->defaultAlias($target),
            'refers' => $refers,
            'display' => $target instanceof Symbol ? $target->getName() : 'unknown',
            'anchor' => $target,
        ], $i];
    }

    private function defaultAlias(mixed $target): string
    {
        if (!$target instanceof Symbol) {
            return '';
        }

        $name = $target->getName();
        $parts = explode('\\', $name);

        return $parts[count($parts) - 1];
    }

    /**
     * @param array<string, true> $usedSymbols
     */
    private function symbolUsed(string $name, array $usedSymbols): bool
    {
        return $name !== '' && isset($usedSymbols[$name]);
    }
}
