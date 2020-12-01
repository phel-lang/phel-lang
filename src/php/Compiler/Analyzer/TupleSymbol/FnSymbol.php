<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\TupleSymbol\ReadModel\FnSymbolTuple;
use Phel\Compiler\Analyzer\WithAnalyzer;
use Phel\Compiler\Ast\FnNode;
use Phel\Compiler\Ast\Node;
use Phel\Compiler\NodeEnvironment;
use Phel\Compiler\RecurFrame;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

final class FnSymbol implements TupleSymbolAnalyzer
{
    use WithAnalyzer;

    public function analyze(Tuple $tuple, NodeEnvironment $env): FnNode
    {
        $this->verifyArguments($tuple);

        $fnSymbolTuple = FnSymbolTuple::createWithTuple($tuple);
        $recurFrame = new RecurFrame($fnSymbolTuple->params());

        return new FnNode(
            $env,
            $fnSymbolTuple->params(),
            $this->analyzeBody($fnSymbolTuple, $recurFrame, $env),
            $this->buildUsesFromEnv($env, $fnSymbolTuple),
            $fnSymbolTuple->isVariadic(),
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

    private function analyzeBody(FnSymbolTuple $fnSymbolTuple, RecurFrame $recurFrame, NodeEnvironment $env): Node
    {
        $tupleBody = $fnSymbolTuple->parentTupleBody();

        $body = empty($fnSymbolTuple->lets())
            ? $this->createDoTupleWithBody($tupleBody)
            : $this->createLetTupleWithBody($fnSymbolTuple, $tupleBody);

        $bodyEnv = $env
            ->withMergedLocals($fnSymbolTuple->params())
            ->withContext(NodeEnvironment::CONTEXT_RETURN)
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
            (new Tuple($fnSymbolTuple->lets(), true))->copyLocationFrom($tupleBody),
            ...$tupleBody
        )->copyLocationFrom($tupleBody);
    }

    private function buildUsesFromEnv(NodeEnvironment $env, FnSymbolTuple $fnSymbolTuple): array
    {
        return array_diff($env->getLocals(), $fnSymbolTuple->params());
    }
}
