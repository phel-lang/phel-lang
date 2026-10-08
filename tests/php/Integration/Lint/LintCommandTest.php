<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lint;

use Phel;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Lint\Infrastructure\Command\LintCommand;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function json_decode;

final class LintCommandTest extends TestCase
{
    use RemoveDirTrait;

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_emits_json_diagnostics_for_unused_binding_fixture(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/unused_binding.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        self::assertContains($exit, [0, 1]);
        $payload = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($payload);

        $codes = array_map(static fn(array $d): string => $d['code'], $payload);
        self::assertContains('phel/unused-binding', $codes);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_repeated_symbol_map_key_is_reported_once(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/duplicate_symbol_key.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        $payload = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($payload);
        self::assertSame(
            [['PHEL203', 4, 7]],
            array_map(static fn(array $d): array => [$d['code'], $d['startLine'], $d['startCol']], $payload),
        );
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_returns_zero_on_clean_fixture(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/clean.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        self::assertSame(0, $exit);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_lints_a_namespace_that_defines_and_calls_an_inline_fn(): void
    {
        // #3055: linting analyses without evaluating, so `:inline` metadata is
        // still the reader's list. Invoking it landed on
        // `PersistentList::__invoke($index)` and aborted the whole run with an
        // uncaught TypeError, losing every diagnostic rather than one file.
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/inline_self_call.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        self::assertSame(0, $exit, $tester->getDisplay());
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_fails_with_invocation_error_on_unknown_format(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/clean.phel'],
            '--format' => 'bogus',
        ]);

        self::assertSame(LintCommand::INVALID, $exit);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_fails_with_invocation_error_when_no_readable_paths(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => ['/nonexistent/path/does/not/exist.phel'],
        ]);

        self::assertSame(LintCommand::INVALID, $exit);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_suppresses_unresolved_symbol_for_known_require_alias(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/require_alias.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        $payload = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($payload);

        $codes = array_map(static fn(array $d): string => $d['code'], $payload);
        self::assertNotContains(
            'phel/unresolved-symbol',
            $codes,
            'Alias-qualified call via (:require :as) must not be flagged as unresolved',
        );
        self::assertSame(0, $exit);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_reports_comment_style_only_for_the_standalone_comment(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/comment_style.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        $payload = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($payload);

        $commentStyle = array_values(array_filter(
            $payload,
            static fn(array $d): bool => $d['code'] === 'phel/comment-style',
        ));

        self::assertCount(1, $commentStyle, 'Only the whole-line `;` comment must be flagged');
        self::assertSame(3, $commentStyle[0]['startLine']);
        self::assertSame(0, $exit, 'Comment style is a warning, so the command still succeeds');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_reports_bindings_named_after_a_core_fn(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/shadowed_core_fn.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        $payload = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($payload);

        $shadowed = array_map(
            static fn(array $d): array => [$d['severity'], $d['startLine'], $d['startCol'], $d['message']],
            array_values(array_filter(
                $payload,
                static fn(array $d): bool => $d['code'] === 'phel/shadowed-core-fn',
            )),
        );

        self::assertSame([
            ['warning', 4, 8, "Binding 'inc' shadows the core function 'phel.core/inc'."],
            ['warning', 7, 12, "Binding 'first' shadows the core function 'phel.core/first'."],
        ], $shadowed);
        self::assertSame(0, $exit, 'A shadowed core function is a warning, so the command still succeeds');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_warns_about_a_static_call_to_a_class_it_cannot_autoload(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/unknown_class.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        $payload = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($payload);

        $unknown = array_map(
            static fn(array $d): array => [$d['severity'], $d['startLine'], $d['message']],
            array_values(array_filter(
                $payload,
                static fn(array $d): bool => $d['code'] === 'phel/unknown-class',
            )),
        );

        self::assertSame([
            ['warning', 4, "Class 'System' in 'System/currentTimeMillis' cannot be autoloaded. System is a Java class, not a PHP one: for System/currentTimeMillis use (php/intval (* 1000 (php/microtime true))), the epoch in milliseconds."],
        ], $unknown);
        self::assertSame(0, $exit, 'A class can still be loaded at runtime, so the command still succeeds');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_github_format_emits_annotation_commands(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/unused_binding.phel'],
            '--format' => 'github',
            '--no-cache' => true,
        ]);

        $out = $tester->getDisplay();
        self::assertStringContainsString('::', $out);
        self::assertMatchesRegularExpression('/^::(error|warning|notice) /m', $out);
    }

    /**
     * Regression test for https://github.com/phel-lang/phel-lang/issues/1541:
     * running `phel lint` with no paths must not re-analyze phel's own bundled
     * stdlib files (reachable because `CommandConfig` prepends phel's internal
     * src dir for runtime namespace resolution). Re-analyzing them caused a
     * `DuplicateDefinitionException` for symbols like `phel\walk/walk`.
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_default_paths_exclude_phel_internal_stdlib(): void
    {
        $originalCwd = getcwd();
        $projectRoot = sys_get_temp_dir() . '/phel-lint-1541-' . uniqid('', true);
        mkdir($projectRoot . '/src', 0o777, true);
        file_put_contents($projectRoot . '/src/clean.phel', "(ns consumer\\clean)\n(defn f [] :ok)\n");

        try {
            chdir($projectRoot);
            Phel::bootstrap($projectRoot);
            Phel::clear();
            Symbol::resetGen();
            GlobalEnvironmentSingleton::initializeNew();

            $tester = new CommandTester(new LintCommand());
            $exit = $tester->execute([
                '--format' => 'json',
                '--no-cache' => true,
            ]);

            self::assertNotSame(
                LintCommand::INVALID,
                $exit,
                'Lint with no paths must not abort from re-binding bundled stdlib symbols. '
                . 'Output: ' . $tester->getDisplay(),
            );
            self::assertStringNotContainsString('already bound', $tester->getDisplay());
        } finally {
            if ($originalCwd !== false) {
                chdir($originalCwd);
            }

            @unlink($projectRoot . '/src/clean.phel');
            if (is_dir($projectRoot . '/src')) {
                $leftovers = scandir($projectRoot . '/src') ?: [];
                foreach ($leftovers as $entry) {
                    if ($entry === '.') {
                        continue;
                    }

                    if ($entry === '..') {
                        continue;
                    }

                    @unlink($projectRoot . '/src/' . $entry);
                }

                @rmdir($projectRoot . '/src');
            }

            @rmdir($projectRoot);
        }
    }

    /**
     * A project file that another linted file `:require`s is evaluated for
     * real while the requiring file is analyzed. Analyzing the required file
     * afterwards used to abort the whole run with
     * `Lint failed: Symbol ... is already bound`, because a re-read looked
     * like a redefinition. Re-reading a source is not a redefinition.
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_lints_a_directory_whose_files_require_each_other(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/CrossRequire'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        $display = $tester->getDisplay();

        self::assertStringNotContainsString('already bound', $display);
        self::assertStringNotContainsString('Lint failed', $display);
        self::assertNotSame(LintCommand::INVALID, $exit, 'Output: ' . $display);

        $payload = json_decode(trim($display), true);
        self::assertIsArray($payload);
        self::assertSame([], $payload);
    }

    /**
     * `definterface` implemented by a `defstruct` in the same file: the
     * generated PHP interface only exists once the form has been emitted AND
     * evaluated, which a lint pass never does.
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_lints_a_defstruct_implementing_an_interface_from_the_same_file(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/local_interface.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        $display = $tester->getDisplay();

        self::assertStringNotContainsString('Lint failed', $display);
        self::assertStringNotContainsString('does not exist', $display);
        self::assertSame(0, $exit, 'Output: ' . $display);
    }

    /**
     * The bad file sits on the configured src dir, so the namespace load that
     * precedes linting used to throw before any file was linted, as a bare
     * console error with no file, line or JSON (#3457).
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_bad_ns_form_is_reported_as_a_diagnostic_and_the_other_files_still_lint(): void
    {
        $root = realpath(sys_get_temp_dir()) . '/phel-lint-bad-ns-' . uniqid();
        mkdir($root . '/src', 0777, true);
        file_put_contents($root . '/src/bad.phel', "(ns app.bad\n  (:require [phel.string :refer :all]))\n");
        file_put_contents($root . '/src/main.phel', "(ns app.main)\n\n(defn f [] (let [unused 1] 2))\n");

        try {
            Phel::bootstrap($root);
            Phel::clear();
            Symbol::resetGen();
            GlobalEnvironmentSingleton::initializeNew();

            $tester = new CommandTester(new LintCommand());
            $exit = $tester->execute([
                'paths' => [$root . '/src'],
                '--format' => 'json',
                '--no-cache' => true,
            ]);
        } finally {
            $this->removeDir($root);
        }

        self::assertSame(1, $exit, $tester->getDisplay());
        $payload = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($payload, $tester->getDisplay());

        $byCode = [];
        foreach ($payload as $diagnostic) {
            $byCode[$diagnostic['code']] = $diagnostic;
        }

        self::assertArrayHasKey('PHEL007', $byCode);
        self::assertStringEndsWith('/src/bad.phel', $byCode['PHEL007']['uri']);
        self::assertSame(2, $byCode['PHEL007']['startLine']);
        self::assertStringContainsString(':refer :all is not supported', (string) $byCode['PHEL007']['message']);
        self::assertArrayHasKey('phel/unused-binding', $byCode, 'the other file is still linted');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_reports_a_refer_of_a_name_the_required_namespace_does_not_define(): void
    {
        $root = realpath(sys_get_temp_dir()) . '/phel-lint-refer-' . uniqid();
        mkdir($root . '/src/app', 0777, true);
        file_put_contents($root . '/src/app/util.phel', "(ns app.util)\n\n(defn f [] 1)\n");
        file_put_contents($root . '/src/app/u4.phel', "(ns app.u4\n  (:require app.util :refer [f nope]))\n\n(f)\n");

        try {
            Phel::bootstrap($root);
            Phel::clear();
            Symbol::resetGen();
            GlobalEnvironmentSingleton::initializeNew();

            $tester = new CommandTester(new LintCommand());
            $exit = $tester->execute([
                'paths' => [$root . '/src/app/u4.phel'],
                '--format' => 'json',
                '--no-cache' => true,
            ]);
        } finally {
            $this->removeDir($root);
        }

        self::assertSame(1, $exit, $tester->getDisplay());
        $payload = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($payload, $tester->getDisplay());
        self::assertSame(
            [['phel/unresolved-refer', "'nope' is referred from app.util, which does not define it.", 2]],
            array_map(static fn(array $d): array => [$d['code'], $d['message'], $d['startLine']], $payload),
        );
    }

    private function bootstrap(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
    }
}
