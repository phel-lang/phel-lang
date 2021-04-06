<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\ReadModel\ForeachSymbolTuple;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;

final class ForeachSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): ForeachNode
    {
        $this->verifyArguments($list);

        /** @var PersistentVectorInterface $foreachTuple */
        $foreachTuple = $list->get(1);
        $foreachSymbolTuple = $this->buildForeachSymbolTuple($foreachTuple, $env);

        $bodyExpr = $this->analyzer->analyze(
            $this->buildTupleBody($foreachSymbolTuple->lets(), $list),
            $foreachSymbolTuple->bodyEnv()->withContext(NodeEnvironmentInterface::CONTEXT_STATEMENT)
        );

        return new ForeachNode(
            $env,
            $bodyExpr,
            $foreachSymbolTuple->listExpr(),
            $foreachSymbolTuple->valueSymbol(),
            $foreachSymbolTuple->keySymbol(),
            $list->getStartLocation()
        );
    }

    private function verifyArguments(PersistentListInterface $list): void
    {
        if (count($list) < 2) {
            throw AnalyzerException::withLocation("At least two arguments are required for 'foreach", $list);
        }

        $foreachTuple = $list->get(1);
        if (!($foreachTuple instanceof PersistentVectorInterface)) {
            throw AnalyzerException::withLocation("First argument of 'foreach must be a vector.", $list);
        }

        $firstArgCount = count($foreachTuple);
        if ($firstArgCount !== 2 && $firstArgCount !== 3) {
            throw AnalyzerException::withLocation("Vector of 'foreach must have exactly two or three elements.", $list);
        }
    }

    private function buildForeachSymbolTuple(PersistentVectorInterface $foreachTuple, NodeEnvironmentInterface $env): ForeachSymbolTuple
    {
        if (count($foreachTuple) === 2) {
            return $this->buildForeachTupleWhen2Args($foreachTuple, $env);
        }

        return $this->buildForeachTupleWhen3Args($foreachTuple, $env);
    }

    private function buildForeachTupleWhen2Args(PersistentVectorInterface $foreachTuple, NodeEnvironmentInterface $env): ForeachSymbolTuple
    {
        $lets = [];
        $valueSymbol = $foreachTuple->get(0);

        if (!($valueSymbol instanceof Symbol)) {
            $tmpSym = Symbol::gen();
            $lets[] = $valueSymbol;
            $lets[] = $tmpSym;
            $valueSymbol = $tmpSym;
        }
        $bodyEnv = $env->withMergedLocals([$valueSymbol]);
        $listExpr = $this->analyzer->analyze(
            $foreachTuple->get(1),
            $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)
        );

        return new ForeachSymbolTuple($lets, $bodyEnv, $listExpr, $valueSymbol);
    }

    private function buildForeachTupleWhen3Args(PersistentVectorInterface $foreachTuple, NodeEnvironmentInterface $env): ForeachSymbolTuple
    {
        $lets = [];
        $keySymbol = $foreachTuple->get(0);
        $valueSymbol = $foreachTuple->get(1);

        if (!($keySymbol instanceof Symbol)) {
            $tmpSym = Symbol::gen();
            $lets[] = $keySymbol;
            $lets[] = $tmpSym;
            $keySymbol = $tmpSym;
        }

        if (!($valueSymbol instanceof Symbol)) {
            $tmpSym = Symbol::gen();
            $lets[] = $valueSymbol;
            $lets[] = $tmpSym;
            $valueSymbol = $tmpSym;
        }

        $bodyEnv = $env->withMergedLocals([$valueSymbol, $keySymbol]);
        $listExpr = $this->analyzer->analyze(
            $foreachTuple->get(2),
            $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)
        );

        return new ForeachSymbolTuple($lets, $bodyEnv, $listExpr, $valueSymbol, $keySymbol);
    }

    private function buildTupleBody(array $lets, PersistentListInterface $list): PersistentListInterface
    {
        $bodys = $list->rest()->rest()->toArray();

        if (!empty($lets)) {
            return TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_LET),
                TypeFactory::getInstance()->persistentVectorFromArray($lets),
                ...$bodys,
            ]);
        }

        return TypeFactory::getInstance()->persistentListFromArray([
            Symbol::create(Symbol::NAME_DO),
            ...$bodys,
        ]);
    }
}
