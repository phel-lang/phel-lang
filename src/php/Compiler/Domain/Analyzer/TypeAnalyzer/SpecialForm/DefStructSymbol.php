<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\DefStructNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;

use function count;

/**
 * (defstruct Name [fields...]).
 *
 * Defines a struct type with named fields and a positional constructor.
 */
final readonly class DefStructSymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private AnalyzerInterface $analyzer,
        private InterfaceImplementationsAnalyzer $implementationsAnalyzer,
    ) {}

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): DefStructNode
    {
        if (count($list) < 3) {
            throw AnalyzerException::withLocation(
                "At least two arguments are required for 'defstruct. Got " . count($list),
                $list,
            );
        }

        $structSymbol = $list->get(1);
        if (!($structSymbol instanceof Symbol)) {
            throw AnalyzerException::wrongArgumentType("First argument of 'defstruct", 'Symbol', $structSymbol, $list);
        }

        $structParams = $list->get(2);
        if (!($structParams instanceof PersistentVectorInterface)) {
            throw AnalyzerException::wrongArgumentType("Second argument of 'defstruct", 'Vector', $structParams, $list);
        }

        $params = $this->params($structParams);

        /** @var PersistentListInterface<mixed> $rest1 */
        $rest1 = $list->rest();
        /** @var PersistentListInterface<mixed> $rest2 */
        $rest2 = $rest1->rest();
        /** @var PersistentListInterface<mixed> $rest3 */
        $rest3 = $rest2->rest();

        return new DefStructNode(
            $env,
            $this->analyzer->getNamespace(),
            $structSymbol,
            $params,
            $this->implementationsAnalyzer->analyze(
                $rest3,
                $env->withMergedLocals($params),
                'defstruct',
            ),
            $list->getStartLocation(),
        );
    }

    /**
     * @param PersistentVectorInterface<mixed> $vector
     *
     * @return list<Symbol>
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
