<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lint\Application\Rule\NamespaceForm;

final class NamespaceFormTest extends RuleTestCase
{
    public function test_find_returns_null_when_no_ns_form_present(): void
    {
        $analysis = $this->buildAnalysis('(def x 1)');

        self::assertNull(NamespaceForm::find($analysis->forms));
    }

    public function test_find_returns_first_ns_form(): void
    {
        $analysis = $this->buildAnalysis("(ns my-app\\core)\n(def x 1)");

        $found = NamespaceForm::find($analysis->forms);

        self::assertInstanceOf(PersistentListInterface::class, $found);
    }

    public function test_collect_symbol_uses_excludes_the_ns_form_itself(): void
    {
        $analysis = $this->buildAnalysis(<<<'PHEL'
            (ns my-app\core)
            (def unused "value")
            (defn other [x] (helper x))
            PHEL);

        $nsForm = NamespaceForm::find($analysis->forms);
        self::assertInstanceOf(PersistentListInterface::class, $nsForm);

        $uses = NamespaceForm::collectSymbolUses($analysis->forms, $nsForm);

        self::assertArrayHasKey('helper', $uses);
        self::assertArrayHasKey('x', $uses);
        // ns form contents (my-app\core) should not leak into uses
        self::assertArrayNotHasKey('my-app\\core', $uses);
    }

    public function test_collect_symbol_uses_records_namespace_part_of_qualified_symbols(): void
    {
        $analysis = $this->buildAnalysis(<<<'PHEL'
            (ns my-app\core)
            (def v (other-ns/helper 1))
            PHEL);

        $nsForm = NamespaceForm::find($analysis->forms);
        self::assertInstanceOf(PersistentListInterface::class, $nsForm);

        $uses = NamespaceForm::collectSymbolUses($analysis->forms, $nsForm);

        self::assertArrayHasKey('other-ns', $uses);
        self::assertArrayHasKey('helper', $uses);
    }
}
