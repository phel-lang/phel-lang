<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\RecurNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class RecurSymbol
{
    use WithAnalyzer;

    public function __invoke(Tuple $tuple, NodeEnvironment $env): RecurNode
    {
        $tupleCount = count($tuple);
        $frame = $env->getCurrentRecurFrame();

        if (!($tuple[0] instanceof Symbol && $tuple[0] == Symbol::NAME_RECUR)) {
            throw AnalyzerException::withLocation("This is not a 'recur.", $tuple);
        }

        if (!$frame) {
            throw AnalyzerException::withLocation("Can't call 'recur here", $tuple[0]);
        }

        if ($tupleCount - 1 !== count($frame->getParams())) {
            throw AnalyzerException::withLocation(
                "Wrong number of arugments for 'recur. Expected: "
                . count($frame->getParams()) . ' args, got: ' . ($tupleCount - 1),
                $tuple
            );
        }

        $frame->setIsActive(true);

        $exprs = [];
        for ($i = 1; $i < $tupleCount; $i++) {
            $exprs[] = $this->analyzer->analyze(
                $tuple[$i],
                $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
            );
        }

        return new RecurNode(
            $env,
            $frame,
            $exprs,
            $tuple->getStartLocation()
        );
    }
}
