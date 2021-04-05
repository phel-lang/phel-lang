<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\MethodCallNode;
use Phel\Compiler\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Analyzer\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

final class PhpObjectCallSymbol implements SpecialFormAnalyzerInterface
{
    private AnalyzerInterface $analyzer;

    private bool $isStatic;

    public function __construct(AnalyzerInterface $analyzer, bool $isStatic)
    {
        $this->analyzer = $analyzer;
        $this->isStatic = $isStatic;
    }

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpObjectCallNode
    {
        $fnName = $this->isStatic
            ? Symbol::NAME_PHP_OBJECT_STATIC_CALL
            : Symbol::NAME_PHP_OBJECT_CALL;

        if (count($list) !== 3) {
            throw AnalyzerException::withLocation("Exactly two arguments are expected for '$fnName", $list);
        }

        if (!($list->get(2) instanceof PersistentListInterface || $list->get(2) instanceof Symbol)) {
            throw AnalyzerException::withLocation("Second argument of '$fnName must be a List or a Symbol", $list);
        }

        $targetExpr = $this->analyzer->analyze(
            $list->get(1),
            $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
        );

        if ($list->get(2) instanceof PersistentListInterface) {
            $methodCall = true;
            $callExpr = $this->callExprForMethodCall($env, $list);
        } else {
            $methodCall = false;
            $callExpr = $this->callExprForPropertyCall($env, $list);
        }

        return new PhpObjectCallNode(
            $env,
            $targetExpr,
            $callExpr,
            $this->isStatic,
            $methodCall,
            $list->getStartLocation()
        );
    }

    private function callExprForMethodCall(NodeEnvironmentInterface $env, PersistentListInterface $list): MethodCallNode
    {
        /** @var PersistentListInterface $list2 */
        $list2 = $list->get(2);
        $args = [];
        for ($forms = $list2->cdr(); $forms != null; $forms = $forms->cdr()) {
            $args[] = $this->analyzer->analyze(
                $forms->first(),
                $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
            );
        }

        /** @psalm-suppress PossiblyNullArgument */
        /** @var Symbol $callSymbol */
        $callSymbol = $list2->get(0);
        return new MethodCallNode($env, $callSymbol, $args, $list2->getStartLocation());
    }

    private function callExprForPropertyCall(NodeEnvironmentInterface $env, PersistentListInterface $list): PropertyOrConstantAccessNode
    {
        /** @var Symbol $list2 */
        $list2 = $list->get(2);

        /** @psalm-suppress PossiblyNullArgument */
        return new PropertyOrConstantAccessNode($env, $list2, $list2->getStartLocation());
    }
}
