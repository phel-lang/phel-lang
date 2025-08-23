<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\MethodCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;
use function sprintf;

final readonly class PhpObjectCallSymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private AnalyzerInterface $analyzer,
        private bool $isStatic,
    ) {
    }

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpObjectCallNode
    {
        $fnName = $this->isStatic
            ? Symbol::NAME_PHP_OBJECT_STATIC_CALL
            : Symbol::NAME_PHP_OBJECT_CALL;

        if (count($list) < 3) {
            throw AnalyzerException::withLocation("At least two arguments are expected for '" . $fnName, $list);
        }

        $targetExpr = $this->analyzer->analyze(
            $list->get(1),
            $env->withExpressionContext()->withDisallowRecurFrame(),
        );
        $counter = count($list);

        for ($i = 2; $i < $counter; ++$i) {
            $current = $list->get($i);

            if (!$current instanceof PersistentListInterface && !$current instanceof Symbol) {
                throw AnalyzerException::withLocation(
                    sprintf("Argument %d of '%s' must be a List or a Symbol", $i, $fnName),
                    $list,
                );
            }

            if ($current instanceof PersistentListInterface) {
                $methodCall = true;
                $callExpr = $this->callExprForMethodCall($env, $current);
            } else {
                $methodCall = false;
                $callExpr = $this->callExprForPropertyCall($env, $current);
            }

            $targetExpr = new PhpObjectCallNode(
                $env,
                $targetExpr,
                $callExpr,
                $this->isStatic && $i === 2,
                $methodCall,
                $current->getStartLocation(),
            );
        }

        /** @var PhpObjectCallNode $targetExpr */
        return $targetExpr;
    }

    private function callExprForMethodCall(NodeEnvironmentInterface $env, PersistentListInterface $segment): MethodCallNode
    {
        $args = [];
        for ($forms = $segment->cdr(); $forms !== null; $forms = $forms->cdr()) {
            $args[] = $this->analyzer->analyze(
                $forms->first(),
                $env->withExpressionContext()->withDisallowRecurFrame(),
            );
        }

        /** @psalm-suppress PossiblyNullArgument */
        /** @var Symbol $callSymbol */
        $callSymbol = $segment->get(0);
        return new MethodCallNode($env, $callSymbol, $args, $segment->getStartLocation());
    }

    private function callExprForPropertyCall(NodeEnvironmentInterface $env, Symbol $segment): PropertyOrConstantAccessNode
    {
        /** @psalm-suppress PossiblyNullArgument */
        return new PropertyOrConstantAccessNode($env, $segment, $segment->getStartLocation());
    }
}
