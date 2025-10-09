<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\InNsNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function is_string;

final class InNsSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): InNsNode
    {
        $listCount = $list->count();

        if ($listCount < 2) {
            throw AnalyzerException::withLocation("'in-ns requires exactly 1 argument (the namespace)", $list);
        }

        if ($listCount > 2) {
            throw AnalyzerException::withLocation("'in-ns requires exactly 1 argument, got " . ($listCount - 1), $list);
        }

        $nsArg = $list->get(1);

        if (!($nsArg instanceof Symbol) && !is_string($nsArg)) {
            throw AnalyzerException::withLocation("First argument of 'in-ns must be a Symbol or String, got: " . get_debug_type($nsArg), $list);
        }

        $ns = $nsArg instanceof Symbol ? $nsArg->getName() : $nsArg;

        if (trim($ns) === '') {
            throw AnalyzerException::withLocation('Namespace cannot be empty', $list);
        }

        // Set the namespace for the analyzer
        $this->analyzer->setNamespace($ns);

        return new InNsNode($ns, $list->getStartLocation());
    }
}
