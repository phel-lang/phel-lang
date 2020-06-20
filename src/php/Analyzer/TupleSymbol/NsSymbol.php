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

    public function __invoke(Tuple $x, NodeEnvironment $env): NsNode
    {
        $tupleCount = count($x);
        if (!($x[1] instanceof Symbol)) {
            throw new AnalyzerException(
                "First argument of 'ns must be a Symbol",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $ns = $x[1]->getName();
        if (!(preg_match("/^[a-zA-Z\x7f-\xff][a-zA-Z0-9\-\x7f-\xff\\\\]*[a-zA-Z0-9\-\x7f-\xff]*$/", $ns))) {
            throw new AnalyzerException(
                'The namespace is not valid. A valid namespace name starts with a letter,
                followed by any number of letters, numbers, or dashes. Elements are splitted by a backslash.',
                $x[1]->getStartLocation(),
                $x[1]->getEndLocation()
            );
        }

        $parts = explode('\\', $ns);
        foreach ($parts as $part) {
            if ($this->isPHPKeyword($part)) {
                throw new AnalyzerException(
                    "The namespace is not valid. The part '$part' can not be used because it is a reserved keyword.",
                    $x[1]->getStartLocation(),
                    $x[1]->getEndLocation()
                );
            }
        }

        $this->analyzer->getGlobalEnvironment()->setNs($ns);

        $requireNs = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $import = $x[$i];

            if (!($import instanceof Tuple)) {
                throw new AnalyzerException(
                    "Import in 'ns must be Tuples.",
                    $x->getStartLocation(),
                    $x->getEndLocation()
                );
            }

            /** @var Tuple $import */
            if ($this->isKeywordWithName($import[0], 'use')) {
                if (!($import[1] instanceof Symbol)) {
                    throw new AnalyzerException(
                        'First arugment in :use must be a symbol.',
                        $import->getStartLocation(),
                        $import->getEndLocation()
                    );
                }

                if (count($import) === 4 && $this->isKeywordWithName($import[2], 'as')) {
                    $alias = $import[3];
                    if (!($alias instanceof Symbol)) {
                        throw new AnalyzerException(
                            'Alias must be a Symbol',
                            $import->getStartLocation(),
                            $import->getEndLocation()
                        );
                    }
                } else {
                    $parts = explode('\\', $import[1]->getName());
                    $alias = Symbol::create($parts[count($parts) - 1]);
                }

                $this->analyzer->getGlobalEnvironment()->addUseAlias($ns, $alias, $import[1]);
            } elseif ($this->isKeywordWithName($import[0], 'require')) {
                if (!($import[1] instanceof Symbol)) {
                    throw new AnalyzerException(
                        'First arugment in :require must be a symbol.',
                        $import->getStartLocation(),
                        $import->getEndLocation()
                    );
                }

                $requireNs[] = $import[1];

                if (count($import) === 4 && $this->isKeywordWithName($import[2], 'as')) {
                    $alias = $import[3];
                    if (!($alias instanceof Symbol)) {
                        throw new AnalyzerException(
                            'Alias must be a Symbol',
                            $import->getStartLocation(),
                            $import->getEndLocation()
                        );
                    }
                } else {
                    $parts = explode('\\', $import[1]->getName());
                    $alias = Symbol::create($parts[count($parts) - 1]);
                }

                $this->analyzer->getGlobalEnvironment()->addRequireAlias($ns, $alias, $import[1]);
            }
        }

        return new NsNode($x[1]->getName(), $requireNs, $x->getStartLocation());
    }

    /** @param mixed $x */
    private function isKeywordWithName($x, string $name): bool
    {
        return $x instanceof Keyword && $x->getName() === $name;
    }
    private function isPHPKeyword(string $w): bool
    {
        return in_array($w, PhpKeywords::KEYWORDS, true);
    }
}
