<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\Ast\NsNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\PhpKeywords;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;

final class NsSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): NsNode
    {
        $listCount = count($list);
        $nsSymbol = $list->get(1);
        if (!($nsSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation("First argument of 'ns must be a Symbol", $list);
        }

        $ns = $nsSymbol->getName();
        if (!(preg_match("/^[a-zA-Z\x7f-\xff][a-zA-Z0-9\-\x7f-\xff\\\\]*[a-zA-Z0-9\-\x7f-\xff]*$/", $ns))) {
            throw AnalyzerException::withLocation(
                'The namespace is not valid. A valid namespace name starts with a letter,
                followed by any number of letters, numbers, or dashes. Elements are splitted by a backslash.',
                $nsSymbol
            );
        }

        $parts = explode('\\', $ns);
        foreach ($parts as $part) {
            if ($this->isPHPKeyword($part)) {
                throw AnalyzerException::withLocation(
                    "The namespace is not valid. The part '{$part}' cannot be used because it is a reserved keyword.",
                    $list
                );
            }
        }

        $this->analyzer->setNamespace($ns);

        $requireNs = [];
        $requireFiles = [];
        for ($forms = $list->rest()->cdr(); $forms != null; $forms = $forms->cdr()) {
            $import = $forms->first();

            if (!($import instanceof PersistentListInterface)) {
                throw AnalyzerException::withLocation("Import in 'ns must be Lists.", $list);
            }

            $value = $import->get(0);

            /** @var PersistentListInterface $import */
            if ($this->isKeywordWithName($value, 'use')) {
                $this->analyzeUse($ns, $import);
            } elseif ($this->isKeywordWithName($value, 'require')) {
                $requireNs[] = $this->analyzeRequire($ns, $import);
            } elseif ($this->isKeywordWithName($value, 'require-file')) {
                $requireFiles[] = $this->analyzeRequireFile($import);
            } elseif ($value instanceof Keyword) {
                throw AnalyzerException::withLocation(
                    "Unexpected keyword {$value->getName()} encountered in 'ns. Expected :use or :require.",
                    $value
                );
            }
        }

        return new NsNode($ns, $requireNs, $requireFiles, $list->getStartLocation());
    }

    private function isPHPKeyword(string $w): bool
    {
        return in_array($w, PhpKeywords::KEYWORDS, true);
    }

    /**
     * @param mixed $x
     */
    private function isKeywordWithName($x, string $name): bool
    {
        return $x instanceof Keyword && $x->getName() === $name;
    }

    private function analyzeUse(string $ns, PersistentListInterface $import): void
    {
        $useSymbol = $import->get(1);
        if (!($useSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation('First argument in :use must be a symbol.', $import);
        }

        if ($useSymbol->getName()[0] !== '\\') {
            $useSymbol = Symbol::createForNamespace($useSymbol->getNamespace(), '\\' . $useSymbol->getName());
        }

        $useData = TypeFactory::getInstance()->persistentMapFromKVs(...$import->toArray());
        $alias = $this->extractAlias($useData, $import, 'use');
        $this->analyzer->addUseAlias($ns, $alias, $useSymbol);
    }

    private function extractAlias(PersistentMapInterface $requireData, PersistentListInterface $import, string $type): Symbol
    {
        $alias = $requireData[Keyword::create('as')];

        if ($alias) {
            if (!($alias instanceof Symbol)) {
                throw AnalyzerException::withLocation('Alias must be a Symbol', $import);
            }
            return $alias;
        }

        $alias2 = $requireData[Keyword::create($type)];

        if ($alias2) {
            if (!($alias2 instanceof Symbol)) {
                throw AnalyzerException::withLocation("First argument in :$type must be a symbol.", $import);
            }
            $parts = explode('\\', $alias2->getName());
            return Symbol::create($parts[count($parts) - 1]);
        }

        throw AnalyzerException::withLocation('Cannot extract alias', $import);
    }

    /**
     * @return Symbol[]
     */
    private function extractRefer(PersistentMapInterface $requireData, PersistentListInterface $import): array
    {
        $refer = $requireData[Keyword::create('refer')];

        if ($refer === null) {
            return [];
        }

        if (!$refer instanceof PersistentVectorInterface) {
            throw AnalyzerException::withLocation('Refer must be a vector', $import);
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

    private function analyzeRequire(string $ns, PersistentListInterface $import): Symbol
    {
        $requireSymbol = $import->get(1);
        if (!($requireSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation('First argument in :require must be a symbol.', $import);
        }

        $requireData = TypeFactory::getInstance()->persistentMapFromKVs(...$import->toArray());
        $alias = $this->extractAlias($requireData, $import, 'require');
        $referSymbols = $this->extractRefer($requireData, $import);

        $this->analyzer->addRequireAlias($ns, $alias, $requireSymbol);
        $this->analyzer->addRefers($ns, $referSymbols, $requireSymbol);

        return $requireSymbol;
    }

    private function analyzeRequireFile(PersistentListInterface $import): string
    {
        $file = $import->get(1);
        if (!is_string($file)) {
            throw AnalyzerException::withLocation('First argument in :require-file must be a string.', $import);
        }

        return $file;
    }
}
