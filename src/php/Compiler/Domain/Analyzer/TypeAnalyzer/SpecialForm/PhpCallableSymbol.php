<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\PhpCallableNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;
use function get_debug_type;
use function sprintf;

/**
 * (php/callable \strlen)        => strlen(...)
 * (php/callable Foo bar)        => Foo::bar(...)
 * (php/callable obj process)    => $obj->process(...).
 *
 * Emits a native PHP 8.1 first-class callable, avoiding the allocation and
 * verbosity of wrapping the target in an `fn` closure.
 */
final readonly class PhpCallableSymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private AnalyzerInterface $analyzer,
    ) {}

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpCallableNode
    {
        $count = count($list);
        if ($count < 2 || $count > 3) {
            throw AnalyzerException::withLocation(
                "One or two arguments are expected for 'php/callable'",
                $list,
            );
        }

        if ($count === 2) {
            return $this->analyzeFreeFunction($list, $env);
        }

        return $this->analyzeMethod($list, $env);
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function analyzeFreeFunction(
        PersistentListInterface $list,
        NodeEnvironmentInterface $env,
    ): PhpCallableNode {
        $fnSymbol = $list->get(1);
        if (!$fnSymbol instanceof Symbol) {
            throw AnalyzerException::withLocation(
                "First argument of 'php/callable' must be a Symbol",
                $list,
            );
        }

        return new PhpCallableNode(
            $env,
            null,
            $fnSymbol->getFullName(),
            isStatic: false,
            sourceLocation: $list->getStartLocation(),
        );
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function analyzeMethod(
        PersistentListInterface $list,
        NodeEnvironmentInterface $env,
    ): PhpCallableNode {
        $target = $list->get(1);
        $methodSymbol = $list->get(2);
        if (!$methodSymbol instanceof Symbol) {
            throw AnalyzerException::withLocation(
                sprintf("Method argument of 'php/callable' must be a Symbol, got %s", get_debug_type($methodSymbol)),
                $list,
            );
        }

        $exprEnv = $env->withExpressionContext()->withDisallowRecurFrame();

        $classNode = $this->resolveClassReference($target, $env, $exprEnv);
        if ($classNode instanceof PhpClassNameNode) {
            return new PhpCallableNode(
                $env,
                $classNode,
                $methodSymbol->getName(),
                isStatic: true,
                sourceLocation: $list->getStartLocation(),
            );
        }

        return new PhpCallableNode(
            $env,
            $this->analyzer->analyze($target, $exprEnv),
            $methodSymbol->getName(),
            isStatic: false,
            sourceLocation: $list->getStartLocation(),
        );
    }

    private function resolveClassReference(
        mixed $target,
        NodeEnvironmentInterface $env,
        NodeEnvironmentInterface $exprEnv,
    ): ?PhpClassNameNode {
        if (!$target instanceof Symbol || $env->hasLocal($target)) {
            return null;
        }

        $resolved = $this->analyzer->resolve($target, $exprEnv);

        return $resolved instanceof PhpClassNameNode ? $resolved : null;
    }
}
