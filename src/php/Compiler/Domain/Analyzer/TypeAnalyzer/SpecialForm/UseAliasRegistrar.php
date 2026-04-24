<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Environment\BackslashSeparatorDeprecator;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

use function count;
use function explode;
use function sprintf;
use function str_contains;
use function str_replace;

final readonly class UseAliasRegistrar
{
    public function __construct(private AnalyzerInterface $analyzer) {}

    /**
     * Walks a `(use Foo\Bar [:as B] Foo\Baz ...)` form and registers every
     * entry as a namespace-local alias. Shared by `(ns ... (:use ...))`
     * (which passes the `:use` keyword at index 0) and by top-level
     * `(use ...)` (which passes the `use` symbol at index 0) — both shapes
     * are skipped via `$startIndex`.
     */
    public function register(
        string $ns,
        PersistentListInterface $form,
        int $startIndex = 1,
        string $label = ':use',
    ): void {
        $elements = $form->toArray();
        $count = count($elements);
        $i = $startIndex;

        while ($i < $count) {
            $useSymbol = $elements[$i];

            if (!($useSymbol instanceof Symbol)) {
                throw AnalyzerException::withLocation(sprintf('First argument in %s must be a symbol.', $label), $form);
            }

            BackslashSeparatorDeprecator::getInstance()->maybeWarn($useSymbol);
            $useSymbol = $this->normalizeSymbolSeparators($useSymbol);

            if ($useSymbol->getName()[0] !== '\\') {
                $useSymbol = Symbol::createForNamespace($useSymbol->getNamespace(), '\\' . $useSymbol->getName())
                    ->copyLocationFrom($useSymbol);
            }

            ++$i;
            $aliasValue = null;

            while ($i < $count) {
                $option = $elements[$i];

                if ($option instanceof Symbol) {
                    break;
                }

                if (!($option instanceof Keyword)) {
                    throw AnalyzerException::withLocation(sprintf('Unexpected argument in %s. Expected a keyword.', $label), $form);
                }

                ++$i;

                if ($option->getName() === 'as') {
                    if ($i >= $count) {
                        throw AnalyzerException::wrongArgumentType('Alias', 'Symbol', null, $form);
                    }

                    $aliasCandidate = $elements[$i];
                    if (!($aliasCandidate instanceof Symbol)) {
                        throw AnalyzerException::wrongArgumentType('Alias', 'Symbol', $aliasCandidate, $form);
                    }

                    $aliasValue = $aliasCandidate;
                    ++$i;

                    continue;
                }

                throw AnalyzerException::withLocation(
                    sprintf('Unexpected keyword %s encountered in %s. Expected :as.', $option->getName(), $label),
                    $option,
                );
            }

            $alias = $this->createAliasFromSymbol($aliasValue, $useSymbol);
            $this->analyzer->addUseAlias($ns, $alias, $useSymbol);
        }
    }

    private function normalizeSymbolSeparators(Symbol $symbol): Symbol
    {
        $name = $symbol->getName();
        if (!str_contains($name, '.')) {
            return $symbol;
        }

        return Symbol::createForNamespace(
            $symbol->getNamespace(),
            str_replace('.', '\\', $name),
        )->copyLocationFrom($symbol);
    }

    private function createAliasFromSymbol(?Symbol $alias, Symbol $symbol): Symbol
    {
        if ($alias instanceof Symbol) {
            return $alias;
        }

        $parts = explode('\\', $symbol->getName());

        return Symbol::create($parts[count($parts) - 1]);
    }
}
