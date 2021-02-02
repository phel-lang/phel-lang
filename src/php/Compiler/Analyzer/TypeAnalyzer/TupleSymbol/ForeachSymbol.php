<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol;

use Phel\Compiler\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\ReadModel\ForeachSymbolTuple;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

final class ForeachSymbol implements TupleSymbolAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): ForeachNode
    {
        $this->verifyArguments($tuple);

        /** @var Tuple $foreachTuple */
        $foreachTuple = $tuple[1];
        $foreachSymbolTuple = $this->buildForeachSymbolTuple($foreachTuple, $env);

        $bodyExpr = $this->analyzer->analyze(
            $this->buildTupleBody($foreachSymbolTuple->lets(), $tuple),
            $foreachSymbolTuple->bodyEnv()->withContext(NodeEnvironmentInterface::CONTEXT_STATEMENT)
        );

        return new ForeachNode(
            $env,
            $bodyExpr,
            $foreachSymbolTuple->listExpr(),
            $foreachSymbolTuple->valueSymbol(),
            $foreachSymbolTuple->keySymbol(),
            $tuple->getStartLocation()
        );
    }

    private function verifyArguments(Tuple $tuple): void
    {
        if (count($tuple) < 2) {
            throw AnalyzerException::withLocation("At least two arguments are required for 'foreach", $tuple);
        }

        $foreachTuple = $tuple[1];
        if (!($foreachTuple instanceof Tuple)) {
            throw AnalyzerException::withLocation("First argument of 'foreach must be a tuple.", $tuple);
        }

        $firstArgCount = count($foreachTuple);
        if ($firstArgCount !== 2 && $firstArgCount !== 3) {
            throw AnalyzerException::withLocation("Tuple of 'foreach must have exactly two or three elements.", $tuple);
        }
    }

    private function buildForeachSymbolTuple(Tuple $foreachTuple, NodeEnvironmentInterface $env): ForeachSymbolTuple
    {
        if (count($foreachTuple) === 2) {
            return $this->buildForeachTupleWhen2Args($foreachTuple, $env);
        }

        return $this->buildForeachTupleWhen3Args($foreachTuple, $env);
    }

    private function buildForeachTupleWhen2Args(Tuple $foreachTuple, NodeEnvironmentInterface $env): ForeachSymbolTuple
    {
        $lets = [];
        $valueSymbol = $foreachTuple[0];

        if (!($valueSymbol instanceof Symbol)) {
            $tmpSym = Symbol::gen();
            $lets[] = $valueSymbol;
            $lets[] = $tmpSym;
            $valueSymbol = $tmpSym;
        }
        $bodyEnv = $env->withMergedLocals([$valueSymbol]);
        $listExpr = $this->analyzer->analyze(
            $foreachTuple[1],
            $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)
        );

        return new ForeachSymbolTuple($lets, $bodyEnv, $listExpr, $valueSymbol);
    }

    private function buildForeachTupleWhen3Args(Tuple $foreachTuple, NodeEnvironmentInterface $env): ForeachSymbolTuple
    {
        $lets = [];
        [$keySymbol, $valueSymbol] = $foreachTuple;

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
            $foreachTuple[2],
            $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)
        );

        return new ForeachSymbolTuple($lets, $bodyEnv, $listExpr, $valueSymbol, $keySymbol);
    }

    private function buildTupleBody(array $lets, Tuple $tuple): Tuple
    {
        $bodys = [];
        for ($i = 2, $iMax = count($tuple); $i < $iMax; $i++) {
            $bodys[] = $tuple[$i];
        }

        if (!empty($lets)) {
            return Tuple::create(
                Symbol::create(Symbol::NAME_LET),
                new Tuple($lets, true),
                ...$bodys
            );
        }

        return Tuple::create(
            Symbol::create(Symbol::NAME_DO),
            ...$bodys
        );
    }
}
