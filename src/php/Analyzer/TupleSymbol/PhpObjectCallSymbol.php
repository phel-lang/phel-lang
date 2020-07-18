<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\MethodCallNode;
use Phel\Ast\PhpObjectCallNode;
use Phel\Ast\PropertyOrConstantAccessNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class PhpObjectCallSymbol
{
    use WithAnalyzer;

    public function toNode(Tuple $tuple, NodeEnvironment $env, bool $isStatic): PhpObjectCallNode
    {
        $fnName = $isStatic
            ? Symbol::NAME_PHP_OBJECT_STATIC_CALL
            : Symbol::NAME_PHP_OBJECT_CALL;

        if (count($tuple) !== 3) {
            throw AnalyzerException::withLocation("Exactly two arguments are expected for '$fnName", $tuple);
        }

        if (!($tuple[2] instanceof Tuple || $tuple[2] instanceof Symbol)) {
            throw AnalyzerException::withLocation("Second argument of '$fnName must be a Tuple or a Symbol", $tuple);
        }

        $targetExpr = $this->analyzer->analyze(
            $tuple[1],
            $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
        );

        if ($tuple[2] instanceof Tuple) {
            $methodCall = true;
            $callExpr = $this->callExprForMethodCall($env, $tuple);
        } else {
            $methodCall = false;
            $callExpr = $this->callExprForPropertyCall($env, $tuple);
        }

        return new PhpObjectCallNode(
            $env,
            $targetExpr,
            $callExpr,
            $isStatic,
            $methodCall,
            $tuple->getStartLocation()
        );
    }

    private function callExprForMethodCall(NodeEnvironment $env, Tuple $tuple): MethodCallNode
    {
        /** @var Tuple $tuple2 */
        $tuple2 = $tuple[2];
        $tCount = count($tuple2);
        $args = [];
        for ($i = 1; $i < $tCount; $i++) {
            $args[] = $this->analyzer->analyze(
                $tuple2[$i],
                $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
            );
        }

        /** @psalm-suppress PossiblyNullArgument */
        return new MethodCallNode($env, $tuple2[0], $args, $tuple2->getStartLocation());
    }

    private function callExprForPropertyCall(NodeEnvironment $env, Tuple $tuple): PropertyOrConstantAccessNode
    {
        /** @var Symbol $tuple2 */
        $tuple2 = $tuple[2];

        /** @psalm-suppress PossiblyNullArgument */
        return new PropertyOrConstantAccessNode($env, $tuple2, $tuple2->getStartLocation());
    }
}
