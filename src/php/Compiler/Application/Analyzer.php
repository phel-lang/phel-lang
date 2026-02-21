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
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentSet;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzePersistentVector;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\AnalyzeSymbol;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
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

final class Analyzer implements AnalyzerInterface
{
    private ?AnalyzePersistentList $listAnalyzer = null;

    private ?AnalyzeSymbol $symbolAnalyzer = null;

    private ?AnalyzePersistentVector $vectorAnalyzer = null;

    private ?AnalyzePersistentSet $setAnalyzer = null;

    private ?AnalyzePersistentMap $mapAnalyzer = null;

    public function __construct(
        private readonly GlobalEnvironmentInterface $globalEnvironment,
        private readonly bool $assertsEnabled = true,
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

    public function getAvailableSymbols(): array
    {
        return $this->globalEnvironment->getAllDefinitions();
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
            $this->symbolAnalyzer ??= new AnalyzeSymbol($this);
            return $this->symbolAnalyzer->analyze($x, $env);
        }

        if ($x instanceof PersistentListInterface) {
            $this->listAnalyzer ??= new AnalyzePersistentList($this, $this->assertsEnabled);
            return $this->listAnalyzer->analyze($x, $env);
        }

        if ($x instanceof PersistentVectorInterface) {
            $this->vectorAnalyzer ??= new AnalyzePersistentVector($this);
            return $this->vectorAnalyzer->analyze($x, $env);
        }

        if ($x instanceof PersistentHashSetInterface) {
            $this->setAnalyzer ??= new AnalyzePersistentSet($this);
            return $this->setAnalyzer->analyze($x, $env);
        }

        if ($x instanceof PersistentMapInterface) {
            $this->mapAnalyzer ??= new AnalyzePersistentMap($this);
            return $this->mapAnalyzer->analyze($x, $env);
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
