<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\UseNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

/**
 * (use ClassName [:as Alias] ...).
 *
 * Registers PHP class aliases in the current namespace without requiring
 * the full `(ns ... (:use ...))` form. Intended for files that join an
 * existing namespace via `(in-ns ...)` and only want to declare the
 * imports they actually use. Pure compile-time registration — emits no
 * runtime code.
 */
final class UseSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): UseNode
    {
        if ($list->count() < 2) {
            throw AnalyzerException::withLocation("'use requires at least one argument", $list);
        }

        $ns = $this->analyzer->getNamespace();

        (new UseAliasRegistrar($this->analyzer))->register($ns, $list, 1, 'use');

        return new UseNode($env, $list->getStartLocation());
    }
}
