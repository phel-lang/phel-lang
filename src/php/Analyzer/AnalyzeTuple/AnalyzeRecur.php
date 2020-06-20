<?php

declare(strict_types=1);

namespace Phel\Analyzer\AnalyzeTuple;

use Phel\Analyzer;
use Phel\Ast\RecurNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzeRecur
{
    private Analyzer $analyzer;

    public function __construct(Analyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function __invoke(Tuple $x, NodeEnvironment $env): RecurNode
    {
        $tupleCount = count($x);
        $frame = $env->getCurrentRecurFrame();

        if (!($x[0] instanceof Symbol && $x[0] == 'recur')) {
            throw new AnalyzerException(
                "This is not a 'recur.",
                $x->getStartLocation(),
                $x->getEndLocation(),
            );
        }

        if (!$frame) {
            throw new AnalyzerException(
                "Can't call 'recur here",
                $x[0]->getStartLocation(),
                $x[0]->getEndLocation()
            );
        }

        if ($tupleCount - 1 !== count($frame->getParams())) {
            throw new AnalyzerException(
                "Wrong number of arugments for 'recur. Expected: "
                . count($frame->getParams()) . ' args, got: ' . ($tupleCount - 1),
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $frame->setIsActive(true);

        $exprs = [];
        for ($i = 1; $i < $tupleCount; $i++) {
            $exprs[] = $this->analyzer->analyze(
                $x[$i],
                $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
            );
        }

        return new RecurNode(
            $env,
            $frame,
            $exprs,
            $x->getStartLocation()
        );
    }
}
