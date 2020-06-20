<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\DefNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class DefSymbol
{
    use WithAnalyzer;

    public function __invoke(Tuple $x, NodeEnvironment $nodeEnvironment): DefNode
    {
        $countX = count($x);
        if ($countX < 3 || $countX > 4) {
            throw new AnalyzerException(
                "Two or three arguments are required for 'def. Got " . count($x),
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!($x[1] instanceof Symbol)) {
            throw new AnalyzerException(
                "First arugment of 'def must be a Symbol.",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $namespace = $this->analyzer->getGlobalEnvironment()->getNs();
        /** @var Symbol $name */
        $name = $x[1];

        $initEnv = $nodeEnvironment
            ->withBoundTo($namespace . '\\' . $name)
            ->withContext(NodeEnvironment::CTX_EXPR)
            ->withDisallowRecurFrame();

        if ($countX === 4) {
            $meta = $x[2];
            $init = $x[3];
        } else {
            $meta = new Table();
            $init = $x[2];
        }

        if (is_string($meta)) {
            $kv = new Keyword('doc');
            $kv->setStartLocation($x->getStartLocation());
            $kv->setEndLocation($x->getEndLocation());

            $meta = Table::fromKVs($kv, $meta);
            $meta->setStartLocation($x->getStartLocation());
            $meta->setEndLocation($x->getEndLocation());
        } elseif ($meta instanceof Keyword) {
            $meta = Table::fromKVs($meta, true);
            $meta->setStartLocation($meta->getStartLocation());
            $meta->setEndLocation($meta->getEndLocation());
        } elseif (!$meta instanceof Table) {
            throw new AnalyzerException(
                'Metadata must be a Symbol, String, Keyword or Table',
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $this->analyzer->getGlobalEnvironment()->addDefinition($namespace, $name, $meta);

        return new DefNode(
            $nodeEnvironment,
            $namespace,
            $name,
            $meta,
            $this->analyzer->analyze($init, $initEnv),
            $x->getStartLocation()
        );
    }
}
