<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol;

use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\ReadModel\FnSymbolTuple;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Compiler\Ast\FnNode;
use Phel\Compiler\Ast\AbstractNode;
use Phel\Compiler\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Ast\RecurFrame;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

final class FnSymbol implements TupleSymbolAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): FnNode
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

    private function analyzeBody(FnSymbolTuple $fnSymbolTuple, RecurFrame $recurFrame, NodeEnvironmentInterface $env): AbstractNode
    {
        $tupleBody = $fnSymbolTuple->parentTupleBody();

        $body = empty($fnSymbolTuple->lets())
            ? $this->createDoTupleWithBody($tupleBody)
            : $this->createLetTupleWithBody($fnSymbolTuple, $tupleBody);

        $bodyEnv = $env
            ->withMergedLocals($fnSymbolTuple->params())
            ->withContext(NodeEnvironmentInterface::CONTEXT_RETURN)
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

    private function buildUsesFromEnv(NodeEnvironmentInterface $env, FnSymbolTuple $fnSymbolTuple): array
    {
        return array_diff($env->getLocals(), $fnSymbolTuple->params());
    }
}
