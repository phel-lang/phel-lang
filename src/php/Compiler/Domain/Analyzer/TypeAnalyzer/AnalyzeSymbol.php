<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\SymbolSuggestionProvider;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function in_array;

final class AnalyzeSymbol
{
    use WithAnalyzerTrait;

    private ?SymbolSuggestionProvider $suggestionProvider = null;

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

        if ($this->isStaticMemberShorthand($symbol)) {
            return $this->analyzer->analyze(
                $this->expandStaticMemberShorthand($symbol),
                $env,
            );
        }

        $suggestions = $this->getSuggestionProvider()->findSimilar(
            $symbol->getName(),
            $this->analyzer->getAvailableSymbols(),
        );

        throw AnalyzerException::cannotResolveSymbol($symbol->getFullName(), $symbol, $suggestions);
    }

    /**
     * Bare `Class/MEMBER` symbol expands to `(php/:: Class MEMBER)` so a static
     * property or constant access reads the same as the list-form static call
     * shorthand handled by AnalyzePersistentList.
     */
    private function isStaticMemberShorthand(Symbol $symbol): bool
    {
        $ns = $symbol->getNamespace();
        if (in_array($ns, [null, '', 'php'], true)) {
            return false;
        }

        $name = $symbol->getName();
        if ($name === '' || !$this->isIdentifierStartChar($name[0])) {
            return false;
        }

        return $ns[0] === '\\'
            || ($ns[0] >= 'A' && $ns[0] <= 'Z');
    }

    private function expandStaticMemberShorthand(Symbol $symbol): PersistentListInterface
    {
        $staticSymbol = Symbol::create(Symbol::NAME_PHP_OBJECT_STATIC_CALL)->copyLocationFrom($symbol);
        $classSymbol = Symbol::create((string) $symbol->getNamespace())->copyLocationFrom($symbol);
        $memberSymbol = Symbol::create($symbol->getName())->copyLocationFrom($symbol);

        return Phel::list([$staticSymbol, $classSymbol, $memberSymbol])->copyLocationFrom($symbol);
    }

    private function isIdentifierStartChar(string $c): bool
    {
        return ($c >= 'a' && $c <= 'z')
            || ($c >= 'A' && $c <= 'Z')
            || $c === '_';
    }

    private function getSuggestionProvider(): SymbolSuggestionProvider
    {
        return $this->suggestionProvider ??= new SymbolSuggestionProvider();
    }
}
