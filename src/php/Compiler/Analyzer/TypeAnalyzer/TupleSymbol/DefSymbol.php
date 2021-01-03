<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol;

use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Compiler\Ast\DefNode;
use Phel\Compiler\Ast\AbstractNode;
use Phel\Compiler\Environment\NodeEnvironmentInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\PhelCodeException;
use Phel\Lang\AbstractType;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;

final class DefSymbol implements TupleSymbolAnalyzerInterface
{
    use WithAnalyzerTrait;

    private const POSSIBLE_TUPLE_SIZES = [3, 4];

    /**
     * @throws PhelCodeException
     */
    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): DefNode
    {
        $this->ensureDefIsAllowed($tuple, $env);
        $this->verifySizeOfTuple($tuple);

        $nameSymbol = $tuple[1];
        if (!($nameSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation("First argument of 'def must be a Symbol.", $tuple);
        }

        $namespace = $this->analyzer->getNamespace();

        [$metaTable, $init] = $this->createMetaTableAndInit($tuple);

        $this->analyzer->addDefinition($namespace, $nameSymbol, $metaTable);

        return new DefNode(
            $env,
            $namespace,
            $nameSymbol,
            $metaTable,
            $this->analyzeInit($init, $env, $namespace, $nameSymbol),
            $tuple->getStartLocation()
        );
    }

    private function ensureDefIsAllowed(Tuple $tuple, NodeEnvironmentInterface $env): void
    {
        if (!$env->isDefAllowed()) {
            throw AnalyzerException::withLocation("'def inside of a 'def is forbidden", $tuple);
        }
    }

    private function verifySizeOfTuple(Tuple $tuple): void
    {
        $tupleSize = count($tuple);

        if (!in_array($tupleSize, self::POSSIBLE_TUPLE_SIZES)) {
            throw AnalyzerException::withLocation(
                "Two or three arguments are required for 'def. Got " . $tupleSize,
                $tuple
            );
        }
    }

    /**
     * @return array{0:Table, 1:mixed}
     */
    private function createMetaTableAndInit(Tuple $tuple): array
    {
        [$meta, $init] = $this->getInitialMetaAndInit($tuple);

        if (!($init instanceof AbstractType)
            && !is_scalar($init)
            && $init !== null
        ) {
            throw AnalyzerException::withLocation('$init must be AbstractType|string|float|int|bool|null', $tuple);
        }

        if (is_string($meta)) {
            $key = (new Keyword('doc'))->copyLocationFrom($tuple);

            return [
                Table::fromKVs($key, $meta)->copyLocationFrom($tuple),
                $init,
            ];
        }

        if ($meta instanceof Keyword) {
            return [
                Table::fromKVs($meta, true)->copyLocationFrom($meta),
                $init,
            ];
        }

        if (!$meta instanceof Table) {
            throw AnalyzerException::withLocation('Metadata must be a String, Keyword or Table', $tuple);
        }

        return [$meta, $init];
    }

    private function getInitialMetaAndInit(Tuple $tuple): array
    {
        if (count($tuple) === 3) {
            return [new Table(), $tuple[2]];
        }

        return [$tuple[2], $tuple[3]];
    }

    /**
     * @param AbstractType|string|float|int|bool|null $init
     */
    private function analyzeInit($init, NodeEnvironmentInterface $env, string $namespace, Symbol $nameSymbol): AbstractNode
    {
        $initEnv = $env
            ->withBoundTo($namespace . '\\' . $nameSymbol)
            ->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)
            ->withDisallowRecurFrame()
            ->withDefAllowed(false);

        return $this->analyzer->analyze($init, $initEnv);
    }
}
