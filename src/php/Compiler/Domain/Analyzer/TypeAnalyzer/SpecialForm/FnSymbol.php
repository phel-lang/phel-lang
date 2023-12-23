<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReadModel\FnSymbolTuple;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;

use function count;

final class FnSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): FnNode
    {
        $this->verifyArguments($list);

        $fnSymbolTuple = FnSymbolTuple::createWithTuple($list);
        $recurFrame = new RecurFrame($fnSymbolTuple->params());

        return new FnNode(
            $env,
            $fnSymbolTuple->params(),
            $this->analyzeBody($fnSymbolTuple, $recurFrame, $env),
            $this->buildUsesFromEnv($env, $fnSymbolTuple),
            $fnSymbolTuple->isVariadic(),
            $recurFrame->isActive(),
            $list->getStartLocation(),
        );
    }

    private function verifyArguments(PersistentListInterface $list): void
    {
        if (count($list) < 2) {
            throw AnalyzerException::withLocation("'fn requires at least one argument", $list);
        }

        if (!($list->get(1) instanceof PersistentVectorInterface)) {
            throw AnalyzerException::withLocation("Second argument of 'fn must be a vector", $list);
        }
    }

    private function analyzeBody(FnSymbolTuple $fnSymbolTuple, RecurFrame $recurFrame, NodeEnvironmentInterface $env): AbstractNode
    {
        $listBody = $fnSymbolTuple->parentListBody();

        $body = $fnSymbolTuple->lets() === []
            ? $this->createDoTupleWithBody($listBody)
            : $this->createLetTupleWithBody($fnSymbolTuple, $listBody);

        $bodyEnv = $env
            ->withMergedLocals($fnSymbolTuple->params())
            ->withReturnContext()
            ->withAddedRecurFrame($recurFrame);

        return $this->analyzer->analyze($body, $bodyEnv);
    }

    /**
     * @param array<int, mixed> $body
     */
    private function createDoTupleWithBody(array $body): PersistentListInterface
    {
        return TypeFactory::getInstance()->persistentListFromArray([
            (Symbol::create(Symbol::NAME_DO))->copyLocationFrom($body),
            ...$body,
        ])->copyLocationFrom($body);
    }

    /**
     * @param array<int, mixed> $listBody
     */
    private function createLetTupleWithBody(FnSymbolTuple $fnSymbolTuple, array $listBody): PersistentListInterface
    {
        return TypeFactory::getInstance()->persistentListFromArray([
            (Symbol::create(Symbol::NAME_LET))->copyLocationFrom($listBody),
            TypeFactory::getInstance()->persistentVectorFromArray($fnSymbolTuple->lets())->copyLocationFrom($listBody),
            ...$listBody,
        ])->copyLocationFrom($listBody);
    }

    private function buildUsesFromEnv(NodeEnvironmentInterface $env, FnSymbolTuple $fnSymbolTuple): array
    {
        return array_values(array_diff($env->getLocals(), $fnSymbolTuple->params()));
    }
}
