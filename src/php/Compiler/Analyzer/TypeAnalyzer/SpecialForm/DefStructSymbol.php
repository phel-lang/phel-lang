<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;

final class DefStructSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): DefStructNode
    {
        if (count($list) !== 3) {
            throw AnalyzerException::withLocation(
                "Exactly two arguments are required for 'defstruct. Got " . count($list),
                $list
            );
        }

        $structSymbol = $list->get(1);
        if (!($structSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation("First argument of 'defstruct must be a Symbol.", $list);
        }

        $structParams = $list->get(2);
        if (!($structParams instanceof PersistentVectorInterface)) {
            throw AnalyzerException::withLocation("Second argument of 'defstruct must be a vector.", $list);
        }

        return new DefStructNode(
            $env,
            $this->analyzer->getNamespace(),
            $structSymbol,
            $this->params($structParams),
            $list->getStartLocation()
        );
    }

    /**
     * @param PersistentVectorInterface<mixed> $vector
     */
    private function params(PersistentVectorInterface $vector): array
    {
        $params = [];
        foreach ($vector as $element) {
            if (!($element instanceof Symbol)) {
                throw AnalyzerException::withLocation('Defstruct field elements must be Symbols.', $vector);
            }
            $params[] = $element;
        }

        return $params;
    }
}
