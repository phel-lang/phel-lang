<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\LoadNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

use function is_string;

final class LoadSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): LoadNode
    {
        $listCount = $list->count();

        if ($listCount < 2) {
            throw AnalyzerException::withLocation("'load requires exactly 1 argument (the file path)", $list);
        }

        if ($listCount > 2) {
            throw AnalyzerException::withLocation("'load requires exactly 1 argument, got " . ($listCount - 1), $list);
        }

        $pathArg = $list->get(1);

        if (!is_string($pathArg)) {
            throw AnalyzerException::withLocation("First argument of 'load must be a string, got: " . get_debug_type($pathArg), $list);
        }

        $callerNamespace = $this->analyzer->getNamespace();

        return new LoadNode($pathArg, $callerNamespace, $list->getStartLocation());
    }
}
