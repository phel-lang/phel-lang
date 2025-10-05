<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\NsNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\PhpKeywords;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use Phel\Shared\ReplConstants;

use function count;
use function explode;
use function in_array;
use function is_string;
use function preg_match;
use function sprintf;

final class NsSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    private const string INVALID_NAMESPACE_MESSAGE = <<<'TXT'
Invalid namespace. A valid namespace name starts with a letter or underscore,
followed by any number of letters, numbers, underscores, or dashes.
Elements are split by a backslash.
TXT;

    private const string NAMESPACE_PART_PATTERN = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\-\x7f-\xff]*$/';

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): NsNode
    {
        $nsSymbol = $list->get(1);
        if (!($nsSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation("First argument of 'ns must be a Symbol", $list);
        }

        $ns = $nsSymbol->getName();
        $parts = explode('\\', $ns);

        $this->assertValidNamespace($parts, $nsSymbol);

        foreach ($parts as $part) {
            if ($this->isPHPKeyword($part)) {
                throw AnalyzerException::withLocation(
                    sprintf("The namespace is not valid. The part '%s' cannot be used because it is a reserved keyword.", $part),
                    $list,
                );
            }
        }

        $this->analyzer->setNamespace($ns);

        $requireNs = [];
        $requireFiles = [];
        for ($forms = $list->rest()->cdr(); $forms !== null; $forms = $forms->cdr()) {
            $import = $forms->first();

            if (!($import instanceof PersistentListInterface)) {
                throw AnalyzerException::withLocation("Import in 'ns must be Lists.", $list);
            }

            $value = $import->get(0);

            /** @var PersistentListInterface $import */
            if ($this->isKeywordWithName($value, 'use')) {
                $this->analyzeUse($ns, $import);
            } elseif ($this->isKeywordWithName($value, 'require')) {
                $requireNs = [...$requireNs, ...$this->analyzeRequire($ns, $import)];
            } elseif ($this->isKeywordWithName($value, 'require-file')) {
                $requireFiles[] = $this->analyzeRequireFile($import);
            } elseif ($value instanceof Keyword) {
                throw AnalyzerException::withLocation(
                    sprintf("Unexpected keyword %s encountered in 'ns. Expected :use or :require.", $value->getName()),
                    $value,
                );
            }
        }

        if (Phel::getDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, ReplConstants::REPL_MODE)) {
            $replSymbol = Symbol::create('phel\\repl');
            $this->analyzer->addRequireAlias($ns, Symbol::create('repl'), $replSymbol);
            $this->analyzer->addRefers(
                $ns,
                [
                    Symbol::create('doc'),
                    Symbol::create('require'),
                    Symbol::create('use'),
                    Symbol::create('print-colorful'),
                    Symbol::create('println-colorful'),
                ],
                $replSymbol,
            );
        }

        return new NsNode($ns, $requireNs, $requireFiles, $list->getStartLocation());
    }

    private function isPHPKeyword(string $w): bool
    {
        return in_array($w, PhpKeywords::KEYWORDS, true);
    }

    private function isKeywordWithName(mixed $x, string $name): bool
    {
        return $x instanceof Keyword && $x->getName() === $name;
    }

    private function analyzeUse(string $ns, PersistentListInterface $import): void
    {
        $elements = $import->toArray();
        $count = count($elements);
        $i = 1;

        while ($i < $count) {
            $useSymbol = $elements[$i];

            if (!($useSymbol instanceof Symbol)) {
                throw AnalyzerException::withLocation('First argument in :use must be a symbol.', $import);
            }

            if ($useSymbol->getName()[0] !== '\\') {
                $useSymbol = Symbol::createForNamespace($useSymbol->getNamespace(), '\\' . $useSymbol->getName());
            }

            ++$i;
            $aliasValue = null;

            while ($i < $count) {
                $option = $elements[$i];

                if ($option instanceof Symbol) {
                    break;
                }

                if (!($option instanceof Keyword)) {
                    throw AnalyzerException::withLocation('Unexpected argument in :use. Expected a keyword.', $import);
                }

                ++$i;

                if ($option->getName() === 'as') {
                    if ($i >= $count) {
                        throw AnalyzerException::withLocation('Alias must be a Symbol', $import);
                    }

                    $aliasCandidate = $elements[$i];
                    if (!($aliasCandidate instanceof Symbol)) {
                        throw AnalyzerException::withLocation('Alias must be a Symbol', $import);
                    }

                    $aliasValue = $aliasCandidate;
                    ++$i;

                    continue;
                }

                throw AnalyzerException::withLocation(
                    sprintf('Unexpected keyword %s encountered in :use. Expected :as.', $option->getName()),
                    $option,
                );
            }

            $alias = $this->createAliasFromSymbol($aliasValue, $useSymbol);
            $this->analyzer->addUseAlias($ns, $alias, $useSymbol);
        }
    }

    /**
     * @return list<Symbol>
     */
    private function extractRefer(?PersistentVectorInterface $refer, PersistentListInterface $import): array
    {
        if (!$refer instanceof PersistentVectorInterface) {
            return [];
        }

        $result = [];
        /** @var PersistentListInterface<mixed> $refer */
        foreach ($refer as $ref) {
            if (!$ref instanceof Symbol) {
                throw AnalyzerException::withLocation('Each refer element must be a Symbol', $import);
            }

            $result[] = $ref;
        }

        return $result;
    }

    private function createAliasFromSymbol(?Symbol $alias, Symbol $symbol): Symbol
    {
        if ($alias instanceof Symbol) {
            return $alias;
        }

        $parts = explode('\\', $symbol->getName());

        return Symbol::create($parts[count($parts) - 1]);
    }

    /**
     * @return list<Symbol>
     */
    private function analyzeRequire(string $ns, PersistentListInterface $import): array
    {
        $elements = $import->toArray();
        $count = count($elements);
        $result = [];

        for ($i = 1; $i < $count; ++$i) {
            $requireSymbol = $elements[$i];
            if (!($requireSymbol instanceof Symbol)) {
                throw AnalyzerException::withLocation('First argument in :require must be a symbol.', $import);
            }

            ++$i;
            $aliasValue = null;
            $referValue = null;

            while ($i < $count) {
                $option = $elements[$i];

                if ($option instanceof Symbol) {
                    break;
                }

                if (!($option instanceof Keyword)) {
                    throw AnalyzerException::withLocation('Unexpected argument in :require. Expected a keyword.', $import);
                }

                ++$i;

                if ($option->getName() === 'as') {
                    if ($i >= $count) {
                        throw AnalyzerException::withLocation('Alias must be a Symbol', $import);
                    }

                    $aliasCandidate = $elements[$i];
                    if (!($aliasCandidate instanceof Symbol)) {
                        throw AnalyzerException::withLocation('Alias must be a Symbol', $import);
                    }

                    $aliasValue = $aliasCandidate;
                    ++$i;

                    continue;
                }

                if ($option->getName() === 'refer') {
                    if ($i >= $count) {
                        throw AnalyzerException::withLocation('Refer must be a vector', $import);
                    }

                    $referCandidate = $elements[$i];
                    if (!$referCandidate instanceof PersistentVectorInterface) {
                        throw AnalyzerException::withLocation('Refer must be a vector', $import);
                    }

                    $referValue = $referCandidate;
                    ++$i;

                    continue;
                }

                throw AnalyzerException::withLocation(
                    sprintf('Unexpected keyword %s encountered in :require. Expected :as or :refer.', $option->getName()),
                    $option,
                );
            }

            $alias = $this->createAliasFromSymbol($aliasValue, $requireSymbol);
            $referSymbols = $this->extractRefer($referValue, $import);

            $this->analyzer->addRequireAlias($ns, $alias, $requireSymbol);
            $this->analyzer->addRefers($ns, $referSymbols, $requireSymbol);

            $result[] = $requireSymbol;

            --$i;
        }

        return $result;
    }

    private function analyzeRequireFile(PersistentListInterface $import): string
    {
        $file = $import->get(1);
        if (!is_string($file)) {
            throw AnalyzerException::withLocation('First argument in :require-file must be a string.', $import);
        }

        return $file;
    }

    /**
     * @param list<string> $parts
     */
    private function assertValidNamespace(array $parts, Symbol $nsSymbol): void
    {
        foreach ($parts as $part) {
            if ($part === '' || !$this->isValidNamespacePart($part)) {
                throw AnalyzerException::withLocation(self::INVALID_NAMESPACE_MESSAGE, $nsSymbol);
            }
        }
    }

    private function isValidNamespacePart(string $part): bool
    {
        return preg_match(self::NAMESPACE_PART_PATTERN, $part) === 1;
    }
}
