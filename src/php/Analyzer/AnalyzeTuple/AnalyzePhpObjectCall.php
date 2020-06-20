<?php

declare(strict_types=1);

namespace Phel\Analyzer\AnalyzeTuple;

use Phel\Analyzer;
use Phel\Ast\MethodCallNode;
use Phel\Ast\PhpObjectCallNode;
use Phel\Ast\PropertyOrConstantAccessNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzePhpObjectCall
{
    private Analyzer $analyzer;

    public function __construct(Analyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function __invoke(Tuple $x, NodeEnvironment $env, bool $isStatic): PhpObjectCallNode
    {
        $fnName = $isStatic ? 'php/::' : 'php/->';
        if (count($x) !== 3) {
            throw new AnalyzerException(
                "Exactly two arguments are expected for '$fnName",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!($x[2] instanceof Tuple || $x[2] instanceof Symbol)) {
            throw new AnalyzerException(
                "Second argument of '$fnName must be a Tuple or a Symbol",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $targetExpr = $this->analyzer->analyze(
            $x[1],
            $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
        );

        if ($x[2] instanceof Tuple) {
            // Method call
            $methodCall = true;

            /** @var Tuple $tuple */
            $tuple = $x[2];
            $tCount = count($tuple);

            if (count($x) < 1) {
                throw new AnalyzerException(
                    'Function name is missing',
                    $x->getStartLocation(),
                    $x->getEndLocation()
                );
            }

            $args = [];
            for ($i = 1; $i < $tCount; $i++) {
                $args[] = $this->analyzer->analyze(
                    $tuple[$i],
                    $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
                );
            }

            /** @psalm-suppress PossiblyNullArgument */
            $callExpr = new MethodCallNode(
                $env,
                $tuple[0],
                $args,
                $tuple->getStartLocation()
            );
        } else {
            // Property call
            $methodCall = false;

            $callExpr = new PropertyOrConstantAccessNode(
                $env,
                $x[2],
                $x[2]->getStartLocation()
            );
        }

        return new PhpObjectCallNode(
            $env,
            $targetExpr,
            $callExpr,
            $isStatic,
            $methodCall,
            $x->getStartLocation()
        );
    }
}
