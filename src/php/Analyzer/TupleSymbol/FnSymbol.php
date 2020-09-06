<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\TupleSymbol\ReadModel\FnSymbolTuple;
use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\FnNode;
use Phel\Ast\Node;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;
use Phel\RecurFrame;

final class FnSymbol implements TupleSymbolAnalyzer
{
    use WithAnalyzer;

    private const STATE_START = 'start';
    private const STATE_REST = 'rest';
    private const STATE_DONE = 'done';

    public function analyze(Tuple $tuple, NodeEnvironment $env): FnNode
    {
        $this->verifyArguments($tuple);

        $fnSymbolTuple = $this->buildFnSymbolTuple($tuple);
        $fnSymbolTuple->addDummyVariadicSymbol();
        $fnSymbolTuple->checkAllVariablesStartWithALetterOrUnderscore($tuple);

        $recurFrame = new RecurFrame($fnSymbolTuple->params);

        return new FnNode(
            $env,
            $fnSymbolTuple->params,
            $this->analyzeBody($fnSymbolTuple, $recurFrame, $env),
            $this->buildUsesFromEnv($env, $fnSymbolTuple),
            $fnSymbolTuple->isVariadic,
            $recurFrame->isActive(),
            $tuple->getStartLocation()
        );
    }

    private function verifyArguments(Tuple $tuple): void
    {
        if (count($tuple) < 2) {
            throw AnalyzerException::withLocation("'fn requires at least one argument", $tuple);
        }

        if (!($tuple[1] instanceof Tuple)) {
            throw AnalyzerException::withLocation("Second argument of 'fn must be a Tuple", $tuple);
        }
    }

    private function buildFnSymbolTuple(Tuple $tuple): FnSymbolTuple
    {
        /** @var Tuple $params */
        $params = $tuple[1];
        $fnSymbolTuple = new FnSymbolTuple($tuple);

        foreach ($params as $param) {
            switch ($fnSymbolTuple->buildParamsState) {
                case self::STATE_START:
                    $fnSymbolTuple->buildParamsStart($param);
                    break;
                case self::STATE_REST:
                    $fnSymbolTuple->buildParamsRest($tuple, $param);
                    break;
                case self::STATE_DONE:
                    $fnSymbolTuple->buildParamsDone($tuple);
            }
        }

        return $fnSymbolTuple;
    }

    private function analyzeBody(FnSymbolTuple $fnSymbolTuple, RecurFrame $recurFrame, NodeEnvironment $env): Node
    {
        $tupleBody = array_slice($fnSymbolTuple->parentTuple->toArray(), 2);

        $body = empty($fnSymbolTuple->lets)
            ? $this->createDoTupleWithBody($tupleBody)
            : $this->createLetTupleWithBody($fnSymbolTuple, $tupleBody);

        $bodyEnv = $env
            ->withMergedLocals($fnSymbolTuple->params)
            ->withContext(NodeEnvironment::CTX_RET)
            ->withAddedRecurFrame($recurFrame);

        return $this->analyzer->analyze($body, $bodyEnv);
    }

    private function createDoTupleWithBody(array $body): Tuple
    {
        return Tuple::create(
            (Symbol::create(Symbol::NAME_DO))->copyLocationFrom($body),
            ...$body
        )->copyLocationFrom($body);
    }

    private function createLetTupleWithBody(FnSymbolTuple $fnSymbolTuple, array $tupleBody): Tuple
    {
        return Tuple::create(
            (Symbol::create(Symbol::NAME_LET))->copyLocationFrom($tupleBody),
            (new Tuple($fnSymbolTuple->lets, true))->copyLocationFrom($tupleBody),
            ...$tupleBody
        )->copyLocationFrom($tupleBody);
    }

    private function buildUsesFromEnv(NodeEnvironment $env, FnSymbolTuple $fnSymbolTuple): array
    {
        return array_diff($env->getLocals(), $fnSymbolTuple->params);
    }
}
