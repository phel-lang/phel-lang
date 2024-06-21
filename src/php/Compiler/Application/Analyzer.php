<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzeLiteral;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentList;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentMap;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentVector;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzeSymbol;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

final readonly class Analyzer implements AnalyzerInterface
{
    public function __construct(
        private GlobalEnvironmentInterface $globalEnvironment,
    ) {
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
     * @param list<Symbol> $referSymbols
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
     * @throws AnalyzerException
     */
    public function analyzeMacro(
        TypeInterface|array|string|float|int|bool|null $x,
        NodeEnvironmentInterface $env,
    ): AbstractNode {
        $this->globalEnvironment->addLevelToAllowPrivateAccess();
        $result = $this->analyze($x, $env);
        $this->globalEnvironment->removeLevelToAllowPrivateAccess();

        return $result;
    }

    /**
     * @throws AnalyzerException
     */
    public function analyze(
        TypeInterface|array|string|float|int|bool|null $x,
        NodeEnvironmentInterface $env,
    ): AbstractNode {
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

    private function isLiteral(float|array|bool|int|string|TypeInterface|null $x): bool
    {
        return is_string($x)
            || is_float($x)
            || is_int($x)
            || is_bool($x)
            || $x === null
            || $x instanceof Keyword
            || is_array($x);
    }
}
