<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\DefNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\AbstractType;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class DefSymbol implements TupleSymbolToNode
{
    use WithAnalyzer;

    public function toNode(Tuple $tuple, NodeEnvironment $env): DefNode
    {
        $countX = count($tuple);
        if ($countX < 3 || $countX > 4) {
            throw AnalyzerException::withLocation(
                "Two or three arguments are required for 'def. Got " . count($tuple),
                $tuple
            );
        }

        if (!($tuple[1] instanceof Symbol)) {
            throw AnalyzerException::withLocation("First argument of 'def must be a Symbol.", $tuple);
        }

        $namespace = $this->analyzer->getGlobalEnvironment()->getNs();
        /** @var Symbol $name */
        $name = $tuple[1];

        $initEnv = $env
            ->withBoundTo($namespace . '\\' . $name)
            ->withContext(NodeEnvironment::CTX_EXPR)
            ->withDisallowRecurFrame();

        [$meta, $init] = $this->createMetaAndInit($tuple);

        $this->analyzer->getGlobalEnvironment()->addDefinition($namespace, $name, $meta);

        return new DefNode(
            $env,
            $namespace,
            $name,
            $meta,
            $this->analyzer->analyze($init, $initEnv),
            $tuple->getStartLocation()
        );
    }

    private function createMetaAndInit(Tuple $tuple): array
    {
        [$meta, $init] = $this->getInitialMetaAndInit($tuple);

        if (!($init instanceof AbstractType) && !is_scalar($init) && $init !== null) {
            throw AnalyzerException::withLocation('$init must be AbstractType|scalar|null', $tuple);
        }

        if (is_string($meta)) {
            $key = (new Keyword('doc'))->copyLocationFrom($tuple);

            return [Table::fromKVs($key, $meta)->copyLocationFrom($tuple), $init];
        }

        if ($meta instanceof Keyword) {
            return [Table::fromKVs($meta, true)->copyLocationFrom($meta), $init];
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
}
