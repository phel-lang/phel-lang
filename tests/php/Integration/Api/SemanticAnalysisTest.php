<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Phel;
use Phel\Api\ApiFacade;
use Phel\Api\Transfer\Completion;
use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\Diagnostic;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SemanticAnalysisTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_reports_invalid_special_form_diagnostic_for_if_with_too_many_args(): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $source = file_get_contents(__DIR__ . '/Fixtures/arity_mismatch.phel');
        self::assertNotFalse($source);

        $diagnostics = $facade->analyzeSource($source, 'arity_mismatch.phel');

        self::assertNotEmpty($diagnostics);
        $codes = array_map(static fn(Diagnostic $d): string => $d->code, $diagnostics);
        self::assertContains('PHEL007', $codes);

        $first = $diagnostics[0];
        self::assertGreaterThan(0, $first->startLine);
        self::assertGreaterThanOrEqual(0, $first->startCol);
        self::assertSame('arity_mismatch.phel', $first->uri);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_reports_unresolved_symbol_diagnostic(): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $diagnostics = $facade->analyzeSource('(ns user) (undef-symbol)', 'user.phel');

        $codes = array_map(static fn(Diagnostic $d): string => $d->code, $diagnostics);
        self::assertContains('PHEL001', $codes);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_indexes_fixture_directory_and_resolves_symbols(): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $index = $facade->indexProject([__DIR__ . '/Fixtures']);

        self::assertGreaterThanOrEqual(4, $index->countDefinitions());
        self::assertGreaterThanOrEqual(2, $index->countNamespaces());

        $greet = $facade->resolveSymbol($index, 'fixtures\\foo', 'greet');
        self::assertNotNull($greet);
        self::assertSame('greet', $greet->name);
        self::assertSame('fixtures\\foo', $greet->namespace);
        self::assertSame(Definition::KIND_DEFN, $greet->kind);

        $noop = $facade->resolveSymbol($index, 'fixtures\\foo', 'noop');
        self::assertNotNull($noop);
        self::assertSame(Definition::KIND_DEFMACRO, $noop->kind);

        $answer = $facade->resolveSymbol($index, 'fixtures\\foo', 'answer');
        self::assertNotNull($answer);
        self::assertSame(Definition::KIND_DEF, $answer->kind);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_finds_references_to_a_symbol_across_files(): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $index = $facade->indexProject([__DIR__ . '/Fixtures']);

        $refs = $facade->findReferences($index, 'fixtures\\foo', 'greet');

        self::assertNotEmpty($refs);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_completes_local_bindings_inside_a_let(): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $source = <<<PHEL
(ns user)
(defn my-fn [x y]
  (let [z (+ x y)
        k 42]
    z))
PHEL;

        $empty = new ProjectIndex([], []);
        $completions = $facade->completeAtPoint($source, 5, 5, $empty);

        $locals = array_values(array_filter(
            $completions,
            static fn(Completion $c): bool => $c->kind === 'local',
        ));
        $labels = array_map(static fn(Completion $c): string => $c->label, $locals);

        self::assertContains('x', $labels);
        self::assertContains('y', $labels);
        self::assertContains('z', $labels);
        self::assertContains('k', $labels);
    }

    private function bootstrap(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
    }
}
