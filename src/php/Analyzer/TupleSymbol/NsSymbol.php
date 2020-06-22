<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\PhpKeywords;
use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\NsNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class NsSymbol
{
    use WithAnalyzer;

    public function __invoke(Tuple $tuple, NodeEnvironment $env): NsNode
    {
        $tupleCount = count($tuple);
        if (!($tuple[1] instanceof Symbol)) {
            throw AnalyzerException::withLocation("First argument of 'ns must be a Symbol", $tuple);
        }

        $ns = $tuple[1]->getName();
        if (!(preg_match("/^[a-zA-Z\x7f-\xff][a-zA-Z0-9\-\x7f-\xff\\\\]*[a-zA-Z0-9\-\x7f-\xff]*$/", $ns))) {
            throw AnalyzerException::withLocation(
                'The namespace is not valid. A valid namespace name starts with a letter,
                followed by any number of letters, numbers, or dashes. Elements are splitted by a backslash.',
                $tuple[1]
            );
        }

        $parts = explode('\\', $ns);
        foreach ($parts as $part) {
            if ($this->isPHPKeyword($part)) {
                throw AnalyzerException::withLocation(
                    "The namespace is not valid. The part '{$part}' can not be used because it is a reserved keyword.",
                    $tuple
                );
            }
        }

        $this->analyzer->getGlobalEnvironment()->setNs($ns);

        $requireNs = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $import = $tuple[$i];

            if (!($import instanceof Tuple)) {
                throw AnalyzerException::withLocation("Import in 'ns must be Tuples.", $tuple);
            }

            /** @var Tuple $import */
            if ($this->isKeywordWithName($import[0], 'use')) {
                $this->analyzeUse($ns, $import);
            } elseif ($this->isKeywordWithName($import[0], 'require')) {
                $requireNs[] = $this->analyzeRequire($ns, $import);
            }
        }

        return new NsNode($tuple[1]->getName(), $requireNs, $tuple->getStartLocation());
    }

    private function isPHPKeyword(string $w): bool
    {
        return in_array($w, PhpKeywords::KEYWORDS, true);
    }

    /** @param mixed $x */
    private function isKeywordWithName($x, string $name): bool
    {
        return $x instanceof Keyword && $x->getName() === $name;
    }

    private function analyzeUse(string $ns, Tuple $import)
    {
        if (!($import[1] instanceof Symbol)) {
            throw AnalyzerException::withLocation('First argument in :use must be a symbol.', $import);
        }

        $alias = $this->extractAlias($import);
        $this->analyzer->getGlobalEnvironment()->addUseAlias($ns, $alias, $import[1]);
    }

    private function extractAlias(Tuple $import): Symbol
    {
        if (count($import) === 4 && $this->isKeywordWithName($import[2], 'as')) {
            $alias = $import[3];
            if (!($alias instanceof Symbol)) {
                throw AnalyzerException::withLocation('Alias must be a Symbol', $import);
            }
            return $alias;
        }

        /** @psalm-suppress PossiblyNullReference */
        $parts = explode('\\', $import[1]->getName());
        return Symbol::create($parts[count($parts) - 1]);
    }

    private function analyzeRequire(string $ns, Tuple $import): Symbol
    {
        if (!($import[1] instanceof Symbol)) {
            throw AnalyzerException::withLocation('First argument in :require must be a symbol.', $import);
        }

        $alias = $this->extractAlias($import);
        $this->analyzer->getGlobalEnvironment()->addRequireAlias($ns, $alias, $import[1]);
        return $import[1];
    }
}
