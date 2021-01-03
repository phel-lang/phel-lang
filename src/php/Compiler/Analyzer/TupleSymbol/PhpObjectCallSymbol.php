<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\AnalyzerInterface;
use Phel\Compiler\Ast\MethodCallNode;
use Phel\Compiler\Ast\PhpObjectCallNode;
use Phel\Compiler\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

final class PhpObjectCallSymbol implements TupleSymbolAnalyzerInterface
{
    private AnalyzerInterface $analyzer;

    private bool $isStatic;

    public function __construct(AnalyzerInterface $analyzer, bool $isStatic)
    {
        $this->analyzer = $analyzer;
        $this->isStatic = $isStatic;
    }

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): PhpObjectCallNode
    {
        $fnName = $this->isStatic
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
            $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
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
            $this->isStatic,
            $methodCall,
            $tuple->getStartLocation()
        );
    }

    private function callExprForMethodCall(NodeEnvironmentInterface $env, Tuple $tuple): MethodCallNode
    {
        /** @var Tuple $tuple2 */
        $tuple2 = $tuple[2];
        $tCount = count($tuple2);
        $args = [];
        for ($i = 1; $i < $tCount; $i++) {
            $args[] = $this->analyzer->analyze(
                $tuple2[$i],
                $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
            );
        }

        /** @psalm-suppress PossiblyNullArgument */
        return new MethodCallNode($env, $tuple2[0], $args, $tuple2->getStartLocation());
    }

    private function callExprForPropertyCall(NodeEnvironmentInterface $env, Tuple $tuple): PropertyOrConstantAccessNode
    {
        /** @var Symbol $tuple2 */
        $tuple2 = $tuple[2];

        /** @psalm-suppress PossiblyNullArgument */
        return new PropertyOrConstantAccessNode($env, $tuple2, $tuple2->getStartLocation());
    }
}
