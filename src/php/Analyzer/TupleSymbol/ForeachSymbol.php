<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\ForeachNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class ForeachSymbol
{
    use WithAnalyzer;

    public function __invoke(Tuple $tuple, NodeEnvironment $env): ForeachNode
    {
        $tupleCount = count($tuple);
        if ($tupleCount < 2) {
            throw AnalyzerException::withLocation("At least two arguments are required for 'foreach", $tuple);
        }

        if (!($tuple[1] instanceof Tuple)) {
            throw AnalyzerException::withLocation("First argument of 'foreach must be a tuple.", $tuple);
        }

        if (count($tuple[1]) !== 2 && count($tuple[1]) !== 3) {
            throw AnalyzerException::withLocation("Tuple of 'foreach must have exactly two or three elements.", $tuple);
        }

        $lets = [];
        if (count($tuple[1]) === 2) {
            $keySymbol = null;

            $valueSymbol = $tuple[1][0];
            if (!($valueSymbol instanceof Symbol)) {
                $tmpSym = Symbol::gen();
                $lets[] = $valueSymbol;
                $lets[] = $tmpSym;
                $valueSymbol = $tmpSym;
            }
            $bodyEnv = $env->withMergedLocals([$valueSymbol]);
            $listExpr = $this->analyzer->analyze(
                $tuple[1][1],
                $env->withContext(NodeEnvironment::CTX_EXPR)
            );
        } else {
            $keySymbol = $tuple[1][0];
            if (!($keySymbol instanceof Symbol)) {
                $tmpSym = Symbol::gen();
                $lets[] = $keySymbol;
                $lets[] = $tmpSym;
                $keySymbol = $tmpSym;
            }

            $valueSymbol = $tuple[1][1];
            if (!($valueSymbol instanceof Symbol)) {
                $tmpSym = Symbol::gen();
                $lets[] = $valueSymbol;
                $lets[] = $tmpSym;
                $valueSymbol = $tmpSym;
            }

            $bodyEnv = $env->withMergedLocals([$valueSymbol, $keySymbol]);
            $listExpr = $this->analyzer->analyze(
                $tuple[1][2],
                $env->withContext(NodeEnvironment::CTX_EXPR)
            );
        }

        $bodys = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $bodys[] = $tuple[$i];
        }

        if (count($lets)) {
            $body = Tuple::create(new Symbol('let'), new Tuple($lets, true), ...$bodys);
        } else {
            $body = Tuple::create(new Symbol('do'), ...$bodys);
        }

        $bodyExpr = $this->analyzer->analyze(
            $body,
            $bodyEnv->withContext(NodeEnvironment::CTX_STMT)
        );

        return new ForeachNode(
            $env,
            $bodyExpr,
            $listExpr,
            $valueSymbol,
            $keySymbol,
            $tuple->getStartLocation()
        );
    }
}
