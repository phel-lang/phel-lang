<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\SymbolSuggestionProvider;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

/**
 * @internal
 */
final class AnalyzeSymbol
{
    use WithAnalyzerTrait;

    private ?SymbolSuggestionProvider $suggestionProvider = null;

    private ?QualifiedMemberExpander $memberExpander = null;

    public function analyze(Symbol $symbol, NodeEnvironmentInterface $env): AbstractNode
    {
        if ($symbol->getNamespace() === 'php') {
            return new PhpVarNode($env, $symbol->getName(), $symbol->getStartLocation());
        }

        if ($env->hasLocal($symbol)) {
            return $this->createLocalVarNode($symbol, $env);
        }

        return $this->createGlobalResolve($symbol, $env);
    }

    private function createLocalVarNode(Symbol $symbol, NodeEnvironmentInterface $env): LocalVarNode
    {
        $shadowedVar = $env->getShadowed($symbol);

        if ($shadowedVar instanceof Symbol) {
            $shadowedVar->copyLocationFrom($symbol);

            return new LocalVarNode($env, $shadowedVar, $symbol->getStartLocation());
        }

        return new LocalVarNode($env, $symbol, $symbol->getStartLocation());
    }

    private function createGlobalResolve(Symbol $symbol, NodeEnvironmentInterface $env): AbstractNode
    {
        $globalResolve = $this->analyzer->resolve($symbol, $env);

        if ($globalResolve instanceof AbstractNode) {
            return $globalResolve;
        }

        $memberForm = $this->getMemberExpander()->expand($symbol, $env);
        if ($memberForm instanceof PersistentListInterface) {
            return $this->analyzer->analyze($memberForm, $env);
        }

        $suggestions = $this->getSuggestionProvider()->findSimilar(
            $symbol->getName(),
            $this->analyzer->getAvailableSymbols(),
        );

        throw AnalyzerException::cannotResolveSymbol($symbol->getFullName(), $symbol, $suggestions);
    }

    private function getMemberExpander(): QualifiedMemberExpander
    {
        return $this->memberExpander ??= new QualifiedMemberExpander($this->analyzer);
    }

    private function getSuggestionProvider(): SymbolSuggestionProvider
    {
        return $this->suggestionProvider ??= new SymbolSuggestionProvider();
    }
}
