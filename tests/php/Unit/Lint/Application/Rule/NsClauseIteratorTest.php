<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lint\Application\Rule\NamespaceForm;
use Phel\Lint\Application\Rule\NsClauseIterator;

use function iterator_to_array;

final class NsClauseIteratorTest extends RuleTestCase
{
    public function test_it_yields_matching_keyword_clauses(): void
    {
        $analysis = $this->buildAnalysis(<<<'PHEL'
            (ns my-app\core
              (:use \DateTime)
              (:require phel\core)
              (:require phel\string :as s))
            PHEL);

        $nsForm = NamespaceForm::find($analysis->forms);
        self::assertInstanceOf(PersistentListInterface::class, $nsForm);

        $requires = iterator_to_array(NsClauseIterator::clauses($nsForm, 'require'), false);
        self::assertCount(2, $requires);

        $uses = iterator_to_array(NsClauseIterator::clauses($nsForm, 'use'), false);
        self::assertCount(1, $uses);
    }

    public function test_it_skips_children_without_a_keyword_head(): void
    {
        $analysis = $this->buildAnalysis(<<<'PHEL'
            (ns my-app\core
              "docstring"
              ()
              (not-a-keyword))
            PHEL);

        $nsForm = NamespaceForm::find($analysis->forms);
        self::assertInstanceOf(PersistentListInterface::class, $nsForm);

        $clauses = iterator_to_array(NsClauseIterator::clauses($nsForm, 'require'), false);

        self::assertSame([], $clauses);
    }

    public function test_it_returns_empty_when_keyword_does_not_match(): void
    {
        $analysis = $this->buildAnalysis('(ns my-app\\core (:require phel\\core))');

        $nsForm = NamespaceForm::find($analysis->forms);
        self::assertInstanceOf(PersistentListInterface::class, $nsForm);

        $clauses = iterator_to_array(NsClauseIterator::clauses($nsForm, 'refer-macros'), false);

        self::assertSame([], $clauses);
    }
}
