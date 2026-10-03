<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use Phel;
use Phel\Command\CommandFacade;
use Phel\Lang\TypeFactory;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Throwable;

use function sprintf;

final class RunCommandTest extends AbstractTestCommand
{
    use CapturesRunCommandOutputTrait;

    public function test_file_not_found(): void
    {
        $this->expectOutputRegex('~Namespace "non-existing-file.phel" not found~');

        $exitCode = $this->createRunCommand()->run(
            $this->stubInput('non-existing-file.phel'),
            $this->stubOutput(),
        );

        self::assertSame(1, $exitCode);
    }

    public function test_run_by_namespace(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->createRunCommand()->run(
            $this->stubInput('test\\test-script'),
            $this->stubOutput(),
        );
    }

    public function test_requiring_a_missing_namespace_fails_instead_of_silently_exiting_zero(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/missing-require-script.phel',
        );

        self::assertStringContainsString("[PHEL014] Cannot find namespace 'some.nonexistent.ns'", $output);
        self::assertStringContainsString("required by 'missing-require-script'", $output);
        self::assertStringContainsString('missing-require-script.phel:2', $output);
        self::assertStringContainsString('searched: ', $output);
        self::assertStringNotContainsString('must not reach here', $output);
    }

    public function test_an_alias_never_required_names_the_namespace_to_require(): void
    {
        $output = $this->captureRunOutput(__DIR__ . '/Fixtures/unrequired-alias-script.phel');

        self::assertStringContainsString(
            "Cannot resolve symbol 'str/join'. No namespace or alias 'str'. Did you mean (:require phel.string :as str)?",
            $output,
        );
        self::assertStringNotContainsString('juxt', $output);
    }

    public function test_requiring_a_misspelled_phel_namespace_fails_with_a_suggestion(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/misspelled-phel-require-script.phel',
        );

        self::assertStringContainsString(
            "Cannot find namespace 'phel.strng' required by 'misspelled-phel-require-script'. Did you mean 'phel.string'?",
            $output,
        );
        self::assertStringNotContainsString('must not reach here', $output);
    }

    public function test_requiring_a_clojure_namespace_with_no_phel_target_fails(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/unknown-clojure-require-script.phel',
        );

        self::assertStringContainsString("Cannot find namespace 'clojure.nothere'", $output);
        self::assertStringNotContainsString('must not reach here', $output);
    }

    /**
     * A sibling whose `ns` form does not analyse is not this script's
     * dependency, so it must not stop the run (#3457).
     */
    public function test_a_bad_ns_form_in_an_unrelated_sibling_does_not_stop_the_run(): void
    {
        $dir = sys_get_temp_dir() . '/phel-bad-sibling-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/bad.phel', "(ns bad-sibling.bad\n  (:require [phel.string :refer :all]))\n");
        file_put_contents($dir . '/main.phel', "(ns bad-sibling.main)\n\n(println \"sibling run ok\")\n");

        try {
            $output = $this->captureRunOutput($dir . '/main.phel');
        } finally {
            unlink($dir . '/bad.phel');
            unlink($dir . '/main.phel');
            rmdir($dir);
        }

        self::assertStringContainsString('sibling run ok', $output);
    }

    /**
     * Written to a temp dir at run time: a committed copy under tests/ is
     * reached by other tests' source scans and fails them all.
     */
    public function test_a_file_not_starting_with_ns_hints_at_the_missing_ns_form(): void
    {
        $dir = sys_get_temp_dir() . '/phel-missing-ns-' . uniqid();
        mkdir($dir);
        $path = $dir . '/missing-ns-script.phel';
        file_put_contents($path, "(defn- helper [] 1)\n\n(ns missing-ns-script)\n\n(println \"must not reach here\" (helper))\n");

        try {
            $output = $this->captureRunOutput($path);
        } finally {
            unlink($path);
            rmdir($dir);
        }

        self::assertStringContainsString("[PHEL001] Cannot resolve symbol 'defn-'", $output);
        self::assertStringContainsString(
            sprintf("hint: '%s' does not start with an (ns ...) form.", $path),
            $output,
        );
        self::assertStringNotContainsString('add (:require ...)', $output);
        self::assertStringNotContainsString('must not reach here', $output);
    }

    /**
     * `require` is a `phel.repl` macro, so in a file it used to fall through to
     * "Did you mean 'reduce'?" (#3476).
     */
    public function test_a_top_level_require_points_at_the_ns_form(): void
    {
        $dir = sys_get_temp_dir() . '/phel-top-level-require-' . uniqid();
        mkdir($dir);
        $path = $dir . '/top-level-require-script.phel';
        file_put_contents($path, "(ns top-level-require-script)\n\n(require phel.string :as s)\n\n(println \"must not reach here\")\n");

        try {
            $output = $this->captureRunOutput($path);
        } finally {
            unlink($path);
            rmdir($dir);
        }

        self::assertStringContainsString(
            "[PHEL001] Cannot resolve symbol 'require': require is only available in the REPL; use (:require ...) inside ns",
            $output,
        );
        self::assertStringNotContainsString('Did you mean', $output);
        self::assertStringNotContainsString('must not reach here', $output);
    }

    public function test_requiring_a_missing_namespace_returns_failure_exit_code(): void
    {
        ob_start();
        $exitCode = $this->createRunCommand()->run(
            $this->stubInput(__DIR__ . '/Fixtures/missing-require-script.phel'),
            $this->stubOutput(),
        );
        ob_end_clean();

        self::assertSame(1, $exitCode);
    }

    public function test_run_by_namespace_accepts_dot_form(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->createRunCommand()->run(
            $this->stubInput('test.test-script'),
            $this->stubOutput(),
        );
    }

    public function test_run_by_filename(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->createRunCommand()->run(
            $this->stubInput(__DIR__ . '/Fixtures/test/test-script.phel'),
            $this->stubOutput(),
        );
    }

    public function test_run_produces_no_duplicate_output(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/test/test-script.phel',
        );

        self::assertSame(1, substr_count($output, 'hello world'), 'Output must not be duplicated on cache miss');
    }

    public function test_run_by_filename_outside_config(): void
    {
        // Must live outside the configured src/test dirs: parallel workers scan
        // those for namespaces and would race with this file's deletion.
        $tmpFile = sys_get_temp_dir() . '/phel-outside-script-' . bin2hex(random_bytes(4)) . '.phel';
        file_put_contents($tmpFile, "(ns outside\script)\n(php/print \"hello world\\n\")");

        try {
            $this->expectOutputRegex('~hello world~');

            $this->createRunCommand()->run(
                $this->stubInput($tmpFile),
                $this->stubOutput(),
            );
        } finally {
            unlink($tmpFile);
        }
    }

    public function test_run_by_filename_resolves_bundled_namespace_fqn_without_require(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/phel-async-fqn-script.phel',
        );

        self::assertStringContainsString('phel.async/delay resolved', $output);
    }

    public function test_run_by_filename_resolves_clojure_test_alias_before_script_eval(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/clojure-test-alias-assert-expr-script.phel',
        );

        self::assertStringContainsString('clojure-test-alias-ok', $output);
    }

    public function test_pass_flag_arguments_to_script(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/argv-script.phel',
            ['--myarg'],
        );

        self::assertMatchesRegularExpression('~first:--myarg~', $output);
    }

    public function test_program_contains_script_path(): void
    {
        $scriptPath = __DIR__ . '/Fixtures/argv-script.phel';

        $output = $this->captureRunOutput($scriptPath, ['arg1']);

        self::assertMatchesRegularExpression(
            '~program:' . preg_quote($scriptPath, '~') . '~',
            $output,
        );
    }

    public function test_argv_does_not_contain_script_name(): void
    {
        $scriptPath = __DIR__ . '/Fixtures/argv-script.phel';

        $output = $this->captureRunOutput($scriptPath, ['arg1', 'arg2']);

        // argv should contain only user args, not the script path
        self::assertStringContainsString('count:2', $output);
        self::assertStringContainsString('first:arg1', $output);
        self::assertStringContainsString('second:arg2', $output);
    }

    public function test_argv_first_element_is_first_user_arg(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/argv-script.phel',
            ['--verbose', 'file.txt'],
        );

        self::assertMatchesRegularExpression('~first:--verbose~', $output);
    }

    public function test_runtime_error_maps_phel_frames_to_source_locations(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/error-trace-script.phel',
        );

        self::assertStringContainsString('boom from error-lib', $output);
        self::assertMatchesRegularExpression('~at .*error-lib\.phel:\d+~', $output);
        self::assertMatchesRegularExpression('~#\d+ .*\.phel:\d+ : \(test\.error-lib/boom-fn~', $output);
        self::assertMatchesRegularExpression('~#\d+ .*\.phel:\d+ : \(test\.error-trace-script/caller~', $output);
        self::assertMatchesRegularExpression('~\.\.\. \d+ internal frames?~', $output);
    }

    public function test_runtime_lib_error_reports_phel_location_and_trace(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/runtime-lib-error-script.phel',
        );

        // The error originates inside the runtime lib (core `+`), which used to
        // cost the report its `at` line and leave the message alone (#3264).
        self::assertStringContainsString('Expected a number, got string', $output);
        self::assertMatchesRegularExpression('~at .*runtime-lib-error-script\.phel:4~', $output);
        self::assertMatchesRegularExpression('~#\d+ .*\.phel:\d+ : \(test\.runtime-lib-error-script/add-boom~', $output);
        self::assertMatchesRegularExpression('~#\d+ .*\.phel:\d+ : \(test\.runtime-lib-error-script/caller~', $output);
        self::assertMatchesRegularExpression('~\.\.\. \d+ internal frames?~', $output);
    }

    public function test_core_library_error_points_at_the_user_call_site(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/core-error-script.phel',
        );

        self::assertStringContainsString('Vector index 5 out of bounds', $output);
        // `nth` raises inside `phel\core`, which the user cannot act on: the
        // `at` line names their own call site instead (#3260).
        self::assertMatchesRegularExpression('~at .*core-error-script\.phel:4~', $output);
        self::assertDoesNotMatchRegularExpression('~at .*sequences\.phel~', $output);
        self::assertStringNotContainsString('cache/compiled', $output);
        self::assertStringNotContainsString('compiled:', $output);
    }

    public function test_recompiled_file_keeps_frames_of_its_first_version_mapped(): void
    {
        $dir = sys_get_temp_dir() . '/phel-recompiled-' . uniqid();
        mkdir($dir);
        $path = $dir . '/recompiled-main.phel';
        file_put_contents($path, "(ns recompiled-main)\n\n(defn read-fifth [xs]\n  (nth xs 5))\n");

        try {
            $this->captureRunOutput($path);
            $this->captureRunOutput($path);
            $firstVersion = Phel::getDefinition('recompiled_main', 'read-fifth');
            file_put_contents($path, "(ns recompiled-main)\n\n;; one\n;; two\n;; three\n\n(defn read-fifth [xs]\n  (nth xs 5))\n");
            $this->captureRunOutput($path);
            $firstReport = $this->runtimeErrorReport($firstVersion);
            $this->captureRunOutput($path);
            $secondReport = $this->runtimeErrorReport(Phel::getDefinition('recompiled_main', 'read-fifth'));
        } finally {
            unlink($path);
            rmdir($dir);
        }

        self::assertMatchesRegularExpression('~#\d+ \S*recompiled-main\.phel:4 : \(phel\.core/nth~', $firstReport);
        self::assertMatchesRegularExpression('~#\d+ \S*recompiled-main\.phel:8 : \(phel\.core/nth~', $secondReport);
    }

    public function test_uncaught_ex_info_prints_its_data(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/ex-info-script.phel',
        );

        self::assertStringContainsString('boom', $output);
        self::assertStringContainsString('data: {:user-id 42, :op :charge}', $output);
        self::assertMatchesRegularExpression('~at .*ex-info-script\.phel:3~', $output);
        self::assertDoesNotMatchRegularExpression('~at .*exceptions\.phel~', $output);
    }

    public function test_runtime_error_maps_phel_frames_on_repeated_run(): void
    {
        $scriptPath = __DIR__ . '/Fixtures/error-trace-script.phel';

        $this->captureRunOutput($scriptPath);
        $output = $this->captureRunOutput($scriptPath);

        self::assertStringContainsString('boom from error-lib', $output);
        self::assertMatchesRegularExpression('~at .*error-lib\.phel:\d+~', $output);
        self::assertMatchesRegularExpression('~#\d+ .*\.phel:\d+ : \(test\.error-lib/boom-fn~', $output);
    }

    public function test_collapse_marker_names_the_flag_and_the_error_log(): void
    {
        $output = $this->captureRunOutput(
            __DIR__ . '/Fixtures/error-trace-script.phel',
        );

        self::assertMatchesRegularExpression(
            '~\.\.\. \d+ internal frames? \(--stack-trace to show, full trace in \S*error\.log\)~',
            $output,
        );
    }

    public function test_stack_trace_option_prints_the_frames_the_default_collapses(): void
    {
        $scriptPath = __DIR__ . '/Fixtures/error-trace-script.phel';

        $collapsed = $this->captureRunOutput($scriptPath);
        $expanded = $this->captureRunOutput($scriptPath, stackTrace: true);

        self::assertStringNotContainsString('RunCommand.php', $collapsed);
        self::assertStringContainsString('RunCommand.php', $expanded);
        self::assertStringNotContainsString('internal frame', $expanded);
        self::assertStringContainsString('boom from error-lib', $expanded);
    }

    public function test_macro_expansion_error_includes_definition_location(): void
    {
        // Written to its own temp directory rather than next to this file: a
        // transient `.phel` inside the test tree is visible to any *other*
        // test process running a namespace scan, which then fails with
        // "Unable to read file" the moment this test deletes it again. Under
        // paratest that is a real, intermittent cross-process failure.
        $tmpDir = sys_get_temp_dir() . '/phel-macro-error-' . bin2hex(random_bytes(8));
        mkdir($tmpDir, 0o777, true);
        $tmpFile = $tmpDir . '/macro-error-script.phel';

        file_put_contents($tmpFile, <<<'PHEL'
(ns test\macro-error-script)

(defmacro broken-macro [x]
  (throw (new \RuntimeException "macro exploded")))

(broken-macro 1)
PHEL);

        try {
            $output = $this->captureRunOutput($tmpFile);
        } finally {
            unlink($tmpFile);
            rmdir($tmpDir);
        }

        self::assertStringContainsString('Error in expanding macro', $output);
        self::assertStringContainsString('Expanding: (broken-macro 1)', $output);
        self::assertStringContainsString('Cause: macro exploded', $output);
        self::assertMatchesRegularExpression('~Defined: .*macro-error-script\.phel:3~', $output);
    }

    private function runtimeErrorReport(mixed $readFifth): string
    {
        self::assertIsCallable($readFifth);

        try {
            $readFifth(TypeFactory::getInstance()->persistentVectorFromArray([]));
        } catch (Throwable $throwable) {
            return new CommandFacade()->getRuntimeErrorReport($throwable);
        }

        self::fail('read-fifth did not throw');
    }

    public function test_referring_a_name_the_required_namespace_does_not_define_fails_at_the_ns_form(): void
    {
        $dir = sys_get_temp_dir() . '/phel-unresolved-refer-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/refer-util.phel', "(ns refer-util)\n\n(defn f [] 1)\n");
        file_put_contents($dir . '/main.phel', "(ns refer-main\n  (:require refer-util :refer [f nope]))\n\n(println \"must not reach here\")\n");

        try {
            $output = $this->captureRunOutput($dir . '/main.phel');
        } finally {
            unlink($dir . '/refer-util.phel');
            unlink($dir . '/main.phel');
            rmdir($dir);
        }

        self::assertStringContainsString("[PHEL013] 'nope' is referred from refer-util, which does not define it.", $output);
        self::assertStringContainsString('main.phel:2', $output);
        self::assertStringNotContainsString('must not reach here', $output);
    }
}
