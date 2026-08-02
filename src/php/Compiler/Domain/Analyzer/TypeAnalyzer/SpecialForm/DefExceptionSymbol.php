<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\DefExceptionNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;
use function sprintf;

/**
 * (defexception Name).
 *
 * Defines a custom exception type extending RuntimeException.
 *
 * @internal
 */
final class DefExceptionSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): DefExceptionNode
    {
        if (count($list) < 2 || count($list) > 3) {
            throw AnalyzerException::withLocation("One or two arguments are required for 'defexception", $list);
        }

        $name = $list->get(1);
        if (!$name instanceof Symbol) {
            throw AnalyzerException::wrongArgumentType("First argument of 'defexception", 'Symbol', $name, $list);
        }

        $parentSymbol = Symbol::create('\\Exception');
        if (count($list) === 3) {
            $parentSymbol = $list->get(2);
            if (!$parentSymbol instanceof Symbol) {
                throw AnalyzerException::wrongArgumentType("Second argument of 'defexception", 'Symbol', $parentSymbol, $list);
            }
        }

        $parent = $this->analyzeParent($parentSymbol, $env, $list);

        return new DefExceptionNode(
            $env,
            $this->analyzer->getNamespace(),
            $name,
            $parent,
            $list->getStartLocation(),
        );
    }

    /**
     * Resolves the parent the way every other class position resolves one.
     *
     * Wrapping the raw symbol in a `PhpClassNameNode` skipped resolution
     * entirely, so `\Exception` worked while the two spellings the language
     * points users at did not: a bare `RuntimeException` emitted an unrooted
     * name and bound to the *current* PHP namespace, and a dotted
     * `Phel.Lang.ExceptionInfo` reached the generated file with its dots and
     * failed to parse (#2936). Going through the analyzer also picks up
     * `(:use ...)` aliases for free.
     *
     * The caller's environment is passed through unchanged: the node is never
     * emitted as an expression, since `DefExceptionEmitter` reads its
     * `getAbsolutePhpName()` directly, so widening the context would only
     * change what the AST looks like.
     *
     * @param PersistentListInterface<mixed> $list
     */
    private function analyzeParent(
        Symbol $parentSymbol,
        NodeEnvironmentInterface $env,
        PersistentListInterface $list,
    ): PhpClassNameNode {
        $parent = $this->analyzer->analyze($parentSymbol, $env);

        if (!$parent instanceof PhpClassNameNode) {
            throw AnalyzerException::withLocation(
                sprintf(
                    "Second argument of 'defexception must name a PHP class, but '%s' resolves to %s",
                    $parentSymbol->getFullName(),
                    $parent::class,
                ),
                $list,
            );
        }

        return $parent;
    }
}
