<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\AnalyzeLiteral;
use Phel\Compiler\Analyzer\TypeAnalyzer\AnalyzePersistentList;
use Phel\Compiler\Analyzer\TypeAnalyzer\AnalyzePersistentMap;
use Phel\Compiler\Analyzer\TypeAnalyzer\AnalyzePersistentVector;
use Phel\Compiler\Analyzer\TypeAnalyzer\AnalyzeSymbol;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

final class Analyzer implements AnalyzerInterface
{
    private GlobalEnvironmentInterface $globalEnvironment;

    public function __construct(GlobalEnvironmentInterface $globalEnvironment)
    {
        $this->globalEnvironment = $globalEnvironment;
    }

    public function resolve(Symbol $name, NodeEnvironmentInterface $env): ?AbstractNode
    {
        return $this->globalEnvironment->resolve($name, $env);
    }

    public function getNamespace(): string
    {
        return $this->globalEnvironment->getNs();
    }

    public function setNamespace(string $ns): void
    {
        $this->globalEnvironment->setNs($ns);
    }

    public function addUseAlias(string $ns, Symbol $alias, Symbol $nsSymbol): void
    {
        $this->globalEnvironment->addUseAlias($ns, $alias, $nsSymbol);
    }

    public function addRequireAlias(string $ns, Symbol $alias, Symbol $nsSymbol): void
    {
        $this->globalEnvironment->addRequireAlias($ns, $alias, $nsSymbol);
    }

    /**
     * @param Symbol[] $referSymbols
     */
    public function addRefers(string $ns, array $referSymbols, Symbol $nsSymbol): void
    {
        foreach ($referSymbols as $referFnName) {
            $this->globalEnvironment->addRefer($ns, $referFnName, $nsSymbol);
        }
    }

    public function addDefinition(string $ns, Symbol $symbol): void
    {
        $this->globalEnvironment->addDefinition($ns, $symbol);
    }

    public function addInterface(string $ns, Symbol $name): void
    {
        $this->globalEnvironment->addInterface($ns, $name);
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $x
     *
     * @throws AnalyzerException
     */
    public function analyzeMacro($x, NodeEnvironmentInterface $env): AbstractNode
    {
        $this->globalEnvironment->setAllowPrivateAccess(true);
        $result = $this->analyze($x, $env);
        $this->globalEnvironment->setAllowPrivateAccess(false);

        return $result;
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $x
     *
     * @throws AnalyzerException
     */
    public function analyze($x, NodeEnvironmentInterface $env): AbstractNode
    {
        if ($this->isLiteral($x)) {
            return (new AnalyzeLiteral())->analyze($x, $env);
        }

        if ($x instanceof Symbol) {
            return (new AnalyzeSymbol($this))->analyze($x, $env);
        }

        if ($x instanceof PersistentListInterface) {
            return (new AnalyzePersistentList($this))->analyze($x, $env);
        }

        if ($x instanceof PersistentVectorInterface) {
            return (new AnalyzePersistentVector($this))->analyze($x, $env);
        }

        if ($x instanceof PersistentMapInterface) {
            return (new AnalyzePersistentMap($this))->analyze($x, $env);
        }

        throw new AnalyzerException('Unhandled type: ' . var_export($x, true));
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $x
     */
    private function isLiteral($x): bool
    {
        return is_string($x)
            || is_float($x)
            || is_int($x)
            || is_bool($x)
            || $x === null
            || $x instanceof Keyword;
    }
}
