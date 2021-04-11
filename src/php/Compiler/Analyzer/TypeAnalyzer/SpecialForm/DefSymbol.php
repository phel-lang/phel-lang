<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\DefNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\TypeInterface;

final class DefSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    private const POSSIBLE_TUPLE_SIZES = [3, 4];

    /**
     * @throws AbstractLocatedException
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): DefNode
    {
        $this->ensureDefIsAllowed($list, $env);
        $this->verifySizeOfTuple($list);

        $nameSymbol = $list->get(1);
        if (!($nameSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation("First argument of 'def must be a Symbol.", $list);
        }

        $namespace = $this->analyzer->getNamespace();

        [$metaTable, $init] = $this->createMetaTableAndInit($list);

        $this->analyzer->addDefinition($namespace, $nameSymbol, $metaTable);

        return new DefNode(
            $env,
            $namespace,
            $nameSymbol,
            $metaTable,
            $this->analyzeInit($init, $env, $namespace, $nameSymbol),
            $list->getStartLocation()
        );
    }

    private function ensureDefIsAllowed(PersistentListInterface $list, NodeEnvironmentInterface $env): void
    {
        if (!$env->isDefAllowed()) {
            throw AnalyzerException::withLocation("'def inside of a 'def is forbidden", $list);
        }
    }

    private function verifySizeOfTuple(PersistentListInterface $list): void
    {
        $listSize = count($list);

        if (!in_array($listSize, self::POSSIBLE_TUPLE_SIZES)) {
            throw AnalyzerException::withLocation(
                "Two or three arguments are required for 'def. Got " . $listSize,
                $list
            );
        }
    }

    /**
     * @return array{0:Table, 1:mixed}
     */
    private function createMetaTableAndInit(PersistentListInterface $list): array
    {
        [$meta, $init] = $this->getInitialMetaAndInit($list);

        if (!($init instanceof TypeInterface)
            && !is_scalar($init)
            && $init !== null
        ) {
            throw AnalyzerException::withLocation('$init must be TypeInterface|string|float|int|bool|null', $list);
        }

        $meta = $this->normalizeMeta($meta, $list);

        $listMeta = $list->getMeta();
        if ($listMeta) {
            foreach ($listMeta->getIterator() as $key => $value) {
                if ($key !== null) {
                    $meta[$key] = $value;
                }
            }
        }

        $startLocation = $list->getStartLocation();
        if ($startLocation) {
            $meta[new Keyword('start-location')] = Table::fromKVs(
                new Keyword('file'),
                $startLocation->getFile(),
                new Keyword('line'),
                $startLocation->getLine(),
                new Keyword('column'),
                $startLocation->getColumn(),
            );
        }

        $endLocation = $list->getEndLocation();
        if ($endLocation) {
            $meta[new Keyword('end-location')] = Table::fromKVs(
                new Keyword('file'),
                $endLocation->getFile(),
                new Keyword('line'),
                $endLocation->getLine(),
                new Keyword('column'),
                $endLocation->getColumn(),
            );
        }

        return [$meta, $init];
    }

    /**
     * @param mixed $meta
     */
    private function normalizeMeta($meta, PersistentListInterface $list): Table
    {
        if (is_string($meta)) {
            $key = (new Keyword('doc'))->copyLocationFrom($list);

            return Table::fromKVs($key, $meta)->copyLocationFrom($list);
        }

        if ($meta instanceof Keyword) {
            return Table::fromKVs($meta, true)->copyLocationFrom($meta);
        }

        if ($meta instanceof Table) {
            return $meta;
        }

        throw AnalyzerException::withLocation('Metadata must be a String, Keyword or Table', $list);
    }

    private function getInitialMetaAndInit(PersistentListInterface $list): array
    {
        if (count($list) === 3) {
            return [new Table(), $list->get(2)];
        }

        return [$list->get(2), $list->get(3)];
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $init
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
