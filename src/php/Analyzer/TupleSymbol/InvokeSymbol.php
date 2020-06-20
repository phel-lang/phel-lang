<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Exception;
use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\CallNode;
use Phel\Ast\GlobalVarNode;
use Phel\Ast\Node;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\AbstractType;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class InvokeSymbol
{
    use WithAnalyzer;

    public function __invoke(Tuple $x, NodeEnvironment $nodeEnvironment): Node
    {
        $tupleCount = count($x);
        $f = $this->analyzer->analyze(
            $x[0],
            $nodeEnvironment->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
        );

        if ($f instanceof GlobalVarNode && $f->isMacro()) {
            $this->analyzer->getGlobalEnvironment()->setAllowPrivateAccess(true);
            $result = $this->analyzer->analyze($this->macroExpand($x, $nodeEnvironment), $nodeEnvironment);
            $this->analyzer->getGlobalEnvironment()->setAllowPrivateAccess(false);

            return $result;
        }

        $arguments = [];
        for ($i = 1; $i < $tupleCount; $i++) {
            $arguments[] = $this->analyzer->analyze(
                $x[$i],
                $nodeEnvironment->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
            );
        }

        return new CallNode(
            $nodeEnvironment,
            $f,
            $arguments,
            $x->getStartLocation()
        );
    }

    /** @return AbstractType|scalar|null */
    private function macroExpand(Tuple $x, NodeEnvironment $env)
    {
        $tupleCount = count($x);
        /** @psalm-suppress PossiblyNullArgument */
        $node = $this->analyzer->getGlobalEnvironment()->resolve($x[0], $env);
        if ($node && $node instanceof GlobalVarNode) {
            $fn = $GLOBALS['__phel'][$node->getNamespace()][$node->getName()->getName()];

            $arguments = [];
            for ($i = 1; $i < $tupleCount; $i++) {
                $arguments[] = $x[$i];
            }

            try {
                $result = $fn(...$arguments);
                $this->enrichLocation($result, $x);

                return $result;
            } catch (Exception $e) {
                throw new AnalyzerException(
                    'Error in expanding macro "' . $node->getNamespace() . '\\' . $node->getName()->getName() . '": ' . $e->getMessage(),
                    $x->getStartLocation(),
                    $x->getEndLocation(),
                    $e
                );
            }
        }

        if (is_null($node)) {
            throw new AnalyzerException(
                'Can not resolve macro',
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        throw new AnalyzerException(
            'This is not macro expandable: ' . get_class($node),
            $x->getStartLocation(),
            $x->getEndLocation()
        );
    }

    /** @param mixed $x */
    private function enrichLocation($x, AbstractType $parent): void
    {
        if ($x instanceof Tuple) {
            foreach ($x as $item) {
                $this->enrichLocation($item, $parent);
            }

            if (!$x->getStartLocation()) {
                $x->setStartLocation($parent->getStartLocation());
            }
            if (!$x->getEndLocation()) {
                $x->setEndLocation($parent->getEndLocation());
            }
        } elseif ($x instanceof AbstractType) {
            if (!$x->getStartLocation()) {
                $x->setStartLocation($parent->getStartLocation());
            }
            if (!$x->getEndLocation()) {
                $x->setEndLocation($parent->getEndLocation());
            }
        }
    }
}
