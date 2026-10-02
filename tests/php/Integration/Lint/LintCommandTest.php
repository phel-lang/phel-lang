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

        self::assertSame(LintCommand::EXIT_INVOCATION_ERROR, $exit);
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

        self::assertSame(LintCommand::EXIT_INVOCATION_ERROR, $exit);
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
                LintCommand::EXIT_INVOCATION_ERROR,
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
        self::assertNotSame(LintCommand::EXIT_INVOCATION_ERROR, $exit, 'Output: ' . $display);

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
