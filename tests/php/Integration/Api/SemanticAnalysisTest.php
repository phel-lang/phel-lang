<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Phel;
use Phel\Api\ApiFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Run\RunFacade;
use Phel\Shared\Api\Completion;
use Phel\Shared\Api\Definition;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\CompilerConstants;
use Phel\Shared\ReplConstants;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SemanticAnalysisTest extends TestCase
{
    use RemoveDirTrait;

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_reports_an_arity_diagnostic_for_if_with_too_many_args(): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $source = file_get_contents(__DIR__ . '/Fixtures/arity_mismatch.phel');
        self::assertNotFalse($source);

        $diagnostics = $facade->analyzeSource($source, 'arity_mismatch.phel');

        self::assertNotEmpty($diagnostics);
        $codes = array_map(static fn(Diagnostic $d): string => $d->code, $diagnostics);
        self::assertContains('PHEL002', $codes);

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
    public function test_a_call_to_a_macro_the_analysis_never_evaluated_is_not_an_error(): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $diagnostics = $facade->analyzeSource(
            "(ns user)\n(defmacro twice [x] `(+ ~x ~x))\n(defn f [] (twice 1))\n(defn g [x] (case x 1 :a 1 :b :d))\n",
            'user.phel',
        );

        self::assertSame(
            [['PHEL005', 4]],
            array_map(static fn(Diagnostic $d): array => [$d->code, $d->startLine], $diagnostics),
        );
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_macro_that_runs_a_fn_the_analysis_never_evaluated_is_not_an_error(): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $path = realpath(__DIR__ . '/Fixtures/macro_runs_caller_code.phel');
        self::assertIsString($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        $diagnostics = $facade->analyzeSource($source, $path);

        self::assertSame([], $diagnostics);
    }

    #[DataProvider('providerMacrosFailingOnTheirOwnInput')]
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_macro_that_fails_on_its_own_input_is_still_an_error(string $form, string $errorCode): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $diagnostics = $facade->analyzeSource("(ns user)\n" . $form . "\n", 'user.phel');

        self::assertSame(
            [[$errorCode, 2]],
            array_map(static fn(Diagnostic $d): array => [$d->code, $d->startLine], $diagnostics),
        );
    }

    public static function providerMacrosFailingOnTheirOwnInput(): iterable
    {
        yield 'defstruct' => ['(defstruct 1 [x])', 'PHEL003'];
        yield 'defrecord' => ['(defrecord 1 [a])', 'PHEL003'];
        yield 'deftype' => ['(deftype 1 [a])', 'PHEL003'];
        yield 'defprotocol' => ['(defprotocol P (1 [this]))', 'PHEL005'];
        yield 'defmulti' => ['(defmulti 1 :k)', 'PHEL005'];
        yield 'defenum' => ['(defenum 1 :a)', 'PHEL003'];
        yield 'definterface' => ['(definterface 1 (foo [this]))', 'PHEL003'];
        yield 'defexception' => ['(defexception 1)', 'PHEL003'];
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_macro_whose_value_is_not_a_fn_is_still_an_error(): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $diagnostics = $facade->analyzeSource("(ns user)\n(def ^{:macro true} fm 1)\n(defn f [] (fm 2))\n", 'user.phel');

        self::assertSame(
            [['PHEL005', 3]],
            array_map(static fn(Diagnostic $d): array => [$d->code, $d->startLine], $diagnostics),
        );
    }

    #[DataProvider('providerSupersededForms')]
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_reports_a_superseded_form_written_in_source(string $form): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $diagnostics = $facade->analyzeSource("(ns user)\n(def v 1)\n" . $form, 'user.phel');

        self::assertCount(1, $diagnostics);
        self::assertSame('PHEL012', $diagnostics[0]->code);
        self::assertSame(3, $diagnostics[0]->startLine);
    }

    public static function providerSupersededForms(): iterable
    {
        yield 'php/new' => ['(php/new \\DateTime)'];
        yield 'php/->' => ['(php/-> (new \\DateTime) (format "Y"))'];
        yield 'php/::' => ['(php/:: \\DateTime createFromFormat "Y" "2024")'];
        yield 'set-var' => ['(set-var (var v) 2)'];
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

        $greet = $facade->resolveSymbol($index, 'fixtures.foo', 'greet');
        self::assertNotNull($greet);
        self::assertSame('greet', $greet->name);
        self::assertSame('fixtures.foo', $greet->namespace);
        self::assertSame(Definition::KIND_DEFN, $greet->kind);

        $noop = $facade->resolveSymbol($index, 'fixtures.foo', 'noop');
        self::assertNotNull($noop);
        self::assertSame(Definition::KIND_DEFMACRO, $noop->kind);

        $answer = $facade->resolveSymbol($index, 'fixtures.foo', 'answer');
        self::assertNotNull($answer);
        self::assertSame(Definition::KIND_DEF, $answer->kind);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_indexes_the_ns_declaration_site_of_each_namespace(): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $index = $facade->indexProject([__DIR__ . '/Fixtures']);

        $location = $index->namespaceLocation('fixtures.foo');
        self::assertNotNull($location);
        self::assertStringEndsWith('foo.phel', $location->uri);
        self::assertSame(1, $location->line);
        self::assertSame($location, $index->namespaceLocation('fixtures\\foo'));

        self::assertContains('fixtures.foo', $index->namespaces());
        self::assertNull($index->namespaceLocation('fixtures.nope'));

        // SymbolKey::resolve($ns, '') builds "$ns/", so a declaration parked under that
        // key would come back out of findReferences and reach rename as an editable range
        foreach (array_keys($index->references) as $key) {
            self::assertStringEndsNotWith('/', $key);
        }

        self::assertSame([], $facade->findReferences($index, 'fixtures.foo', ''));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_backslash_declared_namespace_is_found_by_its_dotted_name(): void
    {
        $this->bootstrap();
        $dir = sys_get_temp_dir() . '/phel-index-backslash-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/legacy.phel', "(ns legacy\\spelling)\n(defn f [] 1)\n");

        $unexpected = [];
        $previousHandler = null;
        $previousHandler = set_error_handler(
            static function (int $level, string $message, string $file, int $line) use (&$unexpected, &$previousHandler): bool {
                if ($level !== E_USER_DEPRECATED) {
                    return $previousHandler !== null && $previousHandler($level, $message, $file, $line) !== false;
                }

                if (!str_starts_with($message, "Backslash ('\\') namespace separator")) {
                    $unexpected[] = $message;
                }

                return true;
            },
        );

        try {
            $index = new ApiFacade()->indexProject([$dir]);
        } finally {
            restore_error_handler();
            unlink($dir . '/legacy.phel');
            rmdir($dir);
        }

        self::assertSame([], $unexpected);
        $location = $index->namespaceLocation('legacy.spelling');
        self::assertNotNull($location);
        self::assertStringEndsWith('legacy.phel', $location->uri);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_finds_references_to_a_symbol_across_files(): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $index = $facade->indexProject([__DIR__ . '/Fixtures']);

        $refs = $facade->findReferences($index, 'fixtures.foo', 'greet');

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

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_completes_iteration_bindings_without_their_collection_expressions(): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();

        $source = <<<PHEL
(ns user)
(defn my-fn []
  (for [item :in coll]
    (foreach [k v other]
      item)))
PHEL;

        $empty = new ProjectIndex([], []);
        // Column 7 is the start of `item`, so the empty prefix leaves every
        // in-scope local in the result.
        $completions = $facade->completeAtPoint($source, 5, 7, $empty);

        $labels = array_map(
            static fn(Completion $c): string => $c->label,
            array_values(array_filter(
                $completions,
                static fn(Completion $c): bool => $c->kind === 'local',
            )),
        );

        self::assertContains('item', $labels);
        self::assertContains('k', $labels);
        self::assertContains('v', $labels);
        // The trailing element of either head is the collection, not a local.
        self::assertNotContains('coll', $labels);
        self::assertNotContains('other', $labels);
    }

    public static function invalidNamespaceForms(): iterable
    {
        $invalidNamespace = '(ns recovery.provider (:require phel.string :as clash) (:require phel.json :as clash))';
        yield 'literal' => [$invalidNamespace, ['PHEL004', 'PHEL001']];
        yield 'nested' => ['(do ' . $invalidNamespace . ')', ['PHEL004', 'PHEL001']];
        yield 'macro' => ["(ns recovery.provider (:require recovery.macros :refer [broken-ns]))\n(broken-ns)", ['PHEL004', 'PHEL001']];
        yield 'loaded' => ["(ns recovery.provider)\n(load \"fragment\")", ['PHEL001']];
    }

    #[DataProvider('invalidNamespaceForms')]
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_an_invalid_namespace_keeps_local_diagnostics_without_publishing_its_definitions(string $namespaceForm, array $expectedCodes): void
    {
        $this->bootstrap();
        $run = new RunFacade();
        $run->loadPhelNamespaces();
        $run->eval("(ns recovery.macros) (defmacro broken-ns [] '(ns recovery.provider (:require phel.string :as clash) (:require phel.json :as clash)))");

        $project = sys_get_temp_dir() . '/phel-namespace-recovery-' . uniqid('', true);
        mkdir($project);

        try {
            $uri = $project . '/provider.phel';
            file_put_contents($project . '/fragment.phel', "(in-ns recovery.provider)\n(ns recovery.provider (:require phel.string :as clash) (:require phel.json :as clash))\n");
            $facade = new ApiFacade();
            self::assertSame([], $facade->analyzeSource("(ns recovery.provider)\n(defn stable [] 1)\n", $uri));

            $diagnostics = $facade->analyzeSource(
                $namespaceForm . "\n(defn stable [x] x)\n(defn leaked [] 1)\n(defn broken [] (missing))\n",
                $uri,
            );

            self::assertSame($expectedCodes, array_map(static fn(Diagnostic $d): ?string => $d->errorCode, $diagnostics));
            self::assertSame(substr_count($namespaceForm, "\n") + 4, array_last($diagnostics)->startLine);
            self::assertSame([], $facade->analyzeSource('(ns recovery.consumer) (recovery.provider/stable)', 'consumer.phel'));
            self::assertSame(
                ['PHEL001'],
                array_map(static fn(Diagnostic $d): ?string => $d->errorCode, $facade->analyzeSource('(ns recovery.consumer) (recovery.provider/leaked)', 'consumer.phel')),
            );
        } finally {
            $this->removeDir($project);
        }
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_rejected_saved_namespace_preserves_previous_aliases(): void
    {
        $this->bootstrap();
        $facade = new ApiFacade();
        self::assertSame([], $facade->analyzeSource('(ns recovery.saved (:require phel.string :as text)) (text/trim "abc")', 'provider.phel'));
        $project = sys_get_temp_dir() . '/phel-saved-namespace-' . uniqid('', true);
        mkdir($project);

        try {
            $uri = $project . '/provider.phel';
            $source = '(ns recovery.saved (:require phel.json :as text) (:require phel.string :as clash) (:require phel.json :as clash))';
            file_put_contents($uri, $source);
            $diagnostics = $facade->analyzeSource($source, $uri);

            self::assertSame(['PHEL004'], array_map(static fn(Diagnostic $d): ?string => $d->errorCode, $diagnostics));
            self::assertSame([], $facade->analyzeSource('(in-ns recovery.saved) (text/trim "abc")', 'consumer.phel'));
        } finally {
            $this->removeDir($project);
        }
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_interactive_eval_keeps_a_redefinition_that_ran_before_a_runtime_error(): void
    {
        $this->bootstrap();
        $run = new RunFacade();
        $run->loadPhelNamespaces();
        Phel::addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, ReplConstants::INTERACTIVE_MODE, true);
        self::assertTrue($run->structuredEval('(ns recovery.interactive) (defn value [^int x] x)')->success);

        $failure = $run->structuredEval('(defn value [^string x] x) (throw (RuntimeException. "boom"))');
        self::assertFalse($failure->success);
        self::assertNotNull($failure->error);
        self::assertSame('boom', $failure->error->message);

        $result = $run->structuredEval('(value "ok")');
        self::assertTrue($result->success, $result->error?->message ?? '');
        self::assertSame('ok', $result->value);
    }

    private function bootstrap(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
    }
}
