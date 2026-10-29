<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Environment\BackslashSeparatorDeprecator;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\PhpClassLike;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\ErrorCode;

use function count;
use function defined;
use function explode;
use function ltrim;
use function sprintf;
use function str_contains;
use function str_replace;
use function strcasecmp;

/**
 * @internal
 */
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
    /**
     * @param PersistentListInterface<mixed> $form
     * @param array<string, Symbol>|null     $aliasesInForm
     */
    public function register(
        string $ns,
        PersistentListInterface $form,
        int $startIndex = 1,
        string $label = ':use',
        ?array &$aliasesInForm = null,
    ): void {
        $elements = $form->toArray();
        $count = count($elements);
        $i = $startIndex;

        while ($i < $count) {
            $useSymbol = $elements[$i];

            if (!($useSymbol instanceof Symbol)) {
                throw AnalyzerException::withLocation(sprintf('First argument in %s must be a symbol.', $label), $form, errorCode: ErrorCode::TYPE_ERROR);
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
                    throw AnalyzerException::withLocation(sprintf('Unexpected argument in %s. Expected a keyword.', $label), $form, errorCode: ErrorCode::TYPE_ERROR);
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

            $useSymbol = UseAliasOrigin::explicit($useSymbol);
            $this->assertImportExists($useSymbol, $form);

            $alias = $this->createAliasFromSymbol($aliasValue, $useSymbol);
            $bound = ($aliasesInForm ?? $this->analyzer->getUseAliases($ns))[$alias->getName()] ?? null;
            if ($bound instanceof Symbol && !UseAliasOrigin::isDefault($bound) && !$this->isSameImport($bound, $useSymbol)) {
                $aliasSource = $aliasValue ?? $useSymbol;
                throw AnalyzerException::withLocation(
                    sprintf("Alias '%s' already names %s in %s, so it cannot also name %s.", $alias->getName(), $bound->getName(), $ns, $useSymbol->getName()),
                    $aliasSource->getStartLocation() instanceof SourceLocation ? $aliasSource : $form,
                    errorCode: ErrorCode::DUPLICATE_DEFINITION,
                );
            }

            $this->analyzer->addUseAlias($ns, $alias, $useSymbol);
            if ($aliasesInForm !== null) {
                $aliasesInForm[$alias->getName()] = $useSymbol;
            }
        }
    }

    private function isSameImport(Symbol $first, Symbol $second): bool
    {
        return $first->getName() === $second->getName()
            || (strcasecmp($first->getName(), $second->getName()) === 0
                && PhpClassLike::exists(ltrim($second->getName(), '\\')));
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

    /**
     * @param PersistentListInterface<mixed> $form
     */
    private function assertImportExists(Symbol $symbol, PersistentListInterface $form): void
    {
        $name = $symbol->getName();

        if ($this->importExists($name)) {
            return;
        }

        throw AnalyzerException::withLocation(sprintf('Cannot import unknown PHP symbol %s.', $name), $form, errorCode: ErrorCode::UNDEFINED_SYMBOL);
    }

    private function importExists(string $name): bool
    {
        $lookupName = ltrim($name, '\\');
        if (PhpClassLike::exists($lookupName)) {
            return true;
        }

        return defined($name);
    }

    private function createAliasFromSymbol(?Symbol $alias, Symbol $symbol): Symbol
    {
        if ($alias instanceof Symbol) {
            return $alias;
        }

        $parts = explode('\\', $symbol->getName());

        return Symbol::create(array_last($parts));
    }
}
