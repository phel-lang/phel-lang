<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Application;

use Phel\Command\Application\RuntimeErrorReportFormatter;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Command\Domain\Exceptions\Extractor\ReadModel\FilePosition;
use Phel\Command\Domain\Exceptions\InternalPathDetector;
use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;
use Phel\Lang\ExceptionInfo;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Exceptions\Hint\NotCallableHint;
use Phel\Shared\Printer\Printer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_shift;

final class RuntimeErrorReportFormatterTest extends TestCase
{
    private const string PHEL_SRC_DIR = '/proj/vendor/phel-lang/phel-lang/src';

    private const string CACHE_DIR = '/proj/.phel/cache';

    private const string STALE_OUTPUT_HINT = 'stale compiled output? try `rm -rf out /var/state/cache` and rebuild.';

    public function test_resolves_compiled_php_to_phel_source_when_source_map_exists(): void
    {
        $compiledPath = '/proj/out/phel/http.php';
        $compiledLine = 387;

        $extractor = $this->createMock(FilePositionExtractorInterface::class);
        $extractor->expects(self::once())
            ->method('getOriginal')
            ->with($compiledPath, $compiledLine)
            ->willReturn(new FilePosition('/proj/src/phel/http.phel', 142));

        $report = $this->createFormatter($extractor)->format(
            $this->errorAt('Value of type null is not callable', $compiledPath, $compiledLine),
        );

        self::assertStringContainsString('Value of type null is not callable', $report);
        self::assertStringContainsString('at /proj/src/phel/http.phel:142', $report);
        self::assertStringContainsString('(compiled: /proj/out/phel/http.php:387)', $report);
    }

    public function test_suppresses_compiled_temp_path_for_ephemeral_eval_file(): void
    {
        // An eval temp file (`__phel_<hash>.php`) is an internal artifact; only
        // the resolved Phel source should be shown, never the temp path (#2638).
        $compiledPath = '/private/var/folders/T/phel/tmp/__phel_abc123.php';

        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/03-not-fn.phel', 3));

        $report = $this->createFormatter($extractor)->format(
            $this->errorAt('Value of type int is not callable', $compiledPath, 4),
        );

        self::assertStringContainsString('Value of type int is not callable', $report);
        self::assertStringContainsString('at /proj/03-not-fn.phel:3', $report);
        self::assertStringNotContainsString('compiled:', $report);
        self::assertStringNotContainsString('__phel_abc123.php', $report);
    }

    public function test_at_line_points_at_the_user_frame_when_the_error_comes_from_the_core_library(): void
    {
        // Innermost first: the throw site inside core, core's own frame, then
        // the user's call site, which is the first one the user can act on.
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturnCallback($this->positionsThenIdentity([
            new FilePosition(self::PHEL_SRC_DIR . '/phel/core/sequences.phel', 220),
            new FilePosition(self::PHEL_SRC_DIR . '/phel/core/sequences.phel', 197),
            new FilePosition('/proj/src/app/main.phel', 2),
        ]));

        $report = $this->createFormatter($extractor)->format($this->errorAt(
            'Vector index 5 out of bounds',
            self::CACHE_DIR . '/compiled/phel.core__e0b15747.php',
            405,
        ));

        self::assertStringContainsString('at /proj/src/app/main.phel:2', $report);
        self::assertStringNotContainsString('sequences.phel', $report);
        // A compiled path pairing the user's position with the core throw site
        // would name the cache artifact here.
        self::assertStringNotContainsString(self::CACHE_DIR, $report);
    }

    public function test_never_names_the_compiled_cache_in_user_facing_output(): void
    {
        // No user frame follows the core throw site, so the `at` line falls
        // back to it, still without naming the regenerable artifact (#3260).
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturnCallback($this->positionsThenIdentity([
            new FilePosition(self::PHEL_SRC_DIR . '/phel/core/sequences.phel', 220),
        ]));

        $report = $this->createFormatter($extractor)->format($this->errorAt(
            'Vector index 5 out of bounds',
            self::CACHE_DIR . '/compiled/phel.core__e0b15747.php',
            405,
        ));

        self::assertStringContainsString('at ' . self::PHEL_SRC_DIR . '/phel/core/sequences.phel:220', $report);
        self::assertStringNotContainsString(self::CACHE_DIR, $report);
        self::assertStringNotContainsString('compiled:', $report);
    }

    public function test_a_throw_from_the_runtime_lib_still_gets_an_at_line(): void
    {
        // `phel run` used to print the message alone here, so this failure was
        // the one report with no location at all to start from (#3264).
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturnCallback($this->positionsThenIdentity([
            new FilePosition(self::PHEL_SRC_DIR . '/php/Lang/NumericCoercion.php', 40),
            new FilePosition('/proj/src/main.phel', 3),
        ]));

        $printer = $this->createStub(ExceptionPrinterInterface::class);
        $printer->method('getUserFacingTraceString')
            ->willReturn("#3 /proj/src/main.phel:3 : (phel\\core\\+ 1 \"boom\")\n   ... 2 internal frames\n");

        $report = $this->createFormatter($extractor, $printer)->format($this->errorAt(
            'Expected a number, got string',
            self::PHEL_SRC_DIR . '/php/Lang/NumericCoercion.php',
            40,
        ));

        self::assertStringContainsString('Expected a number, got string', $report);
        self::assertStringContainsString('at /proj/src/main.phel:3', $report);
        self::assertStringContainsString('#3 /proj/src/main.phel:3 : (phel\\core\\+ 1 "boom")', $report);
        self::assertStringContainsString('... 2 internal frames', $report);
        self::assertStringNotContainsString('rm -rf out', $report);
    }

    public function test_falls_back_to_the_throw_site_when_no_phel_frame_exists(): void
    {
        // Nothing in the trace belongs to the user, so the report keeps its `at`
        // line rather than dropping a section, and names the only location it
        // has. The stale-output hint stays off: Phel's own PHP is no rebuild.
        $throwSite = self::PHEL_SRC_DIR . '/php/Lang/NumericCoercion.php';

        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturnCallback(
            static fn(string $file, int $line): FilePosition => new FilePosition($file, $line),
        );

        $report = $this->createFormatter($extractor)->format($this->errorAt('boom', $throwSite, 40));

        self::assertStringContainsString('at ' . $throwSite . ':40', $report);
        self::assertStringNotContainsString('rm -rf out', $report);
    }

    public function test_anchors_on_the_prompt_for_code_typed_at_the_repl(): void
    {
        // Nothing the user typed at the prompt has a file to open, so the `at`
        // line names the prompt the way the trace frames do.
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturnCallback($this->positionsThenIdentity([
            new FilePosition(self::PHEL_SRC_DIR . '/phel/core/sequences.phel', 220),
            new FilePosition("/proj/src/php/Compiler/InMemoryEvaluator.php(26) : eval()'d code", 1),
        ]));

        $report = $this->createFormatter($extractor)->format($this->errorAt(
            'Vector index 5 out of bounds',
            self::PHEL_SRC_DIR . '/php/Lang/PersistentVector.php',
            220,
        ));

        self::assertStringContainsString('  at repl', $report);
        self::assertStringNotContainsString('InMemoryEvaluator', $report);
    }

    public function test_prints_the_data_map_of_an_uncaught_ex_info(): void
    {
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/app/main.phel', 2));

        $data = TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('user-id'), 42);

        $report = $this->createFormatter($extractor)
            ->format(new RuntimeException('wrapper', 0, new ExceptionInfo('boom', $data)));

        self::assertStringContainsString('boom', $report);
        self::assertStringContainsString('data: {:user-id 42}', $report);
    }

    public function test_an_ex_info_carrying_a_cause_is_reported_over_its_cause(): void
    {
        // `(ex-info msg data cause)` fills the previous slot the compiled-code
        // wrappers use, so unwrapping it reported the cause's message under the
        // `ex-info`'s own data map.
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/app/main.phel', 2));

        $data = TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('user-id'), 42);
        $exInfo = new ExceptionInfo('charging failed', $data, new RuntimeException('connection reset'));

        $report = $this->createFormatter($extractor)->format($exInfo);

        self::assertStringStartsWith('charging failed', $report);
        self::assertStringContainsString('data: {:user-id 42}', $report);
        self::assertStringNotContainsString('connection reset', $report);
    }

    public function test_unwraps_an_evaluated_code_exception_so_the_wrapper_never_shows(): void
    {
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/app/main.phel', 2));

        $wrapped = EvaluatedCodeException::fromThrowableAndCompiledCode(
            new RuntimeException('boom from the prompt'),
            "// some.phel\n",
        );

        $report = $this->createFormatter($extractor)->format($wrapped);

        self::assertStringStartsWith('boom from the prompt', $report);
        self::assertStringNotContainsString('EvaluatedCodeException', $report);
    }

    public function test_omits_the_data_line_for_an_ex_info_without_data(): void
    {
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/app/main.phel', 2));

        $emptyData = TypeFactory::getInstance()->persistentMapFromArray();

        $report = $this->createFormatter($extractor)
            ->format(new RuntimeException('wrapper', 0, new ExceptionInfo('boom', $emptyData)));

        self::assertStringNotContainsString('data:', $report);
    }

    public function test_emits_stale_output_hint_when_source_map_missing(): void
    {
        $compiledPath = '/proj/out/phel/http.php';

        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        // Same filename back => no source map resolution
        $extractor->method('getOriginal')->willReturn(new FilePosition($compiledPath, 12));

        $report = $this->createFormatter($extractor)->format($this->errorAt('boom', $compiledPath, 12));

        self::assertStringContainsString('at /proj/out/phel/http.php:12', $report);
        self::assertStringContainsString('hint: ' . self::STALE_OUTPUT_HINT, $report);
    }

    public function test_emits_actionable_hint_for_known_error(): void
    {
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/main.phel', 3));

        $report = $this->createFormatter(
            $extractor,
            null,
            new ExceptionHintResolver([new NotCallableHint()]),
        )->format($this->errorAt(
            'Object of type Phel\\Lang\\Collections\\Vector\\PersistentVector is not callable',
            '/proj/src/main.phel',
            3,
        ));

        self::assertStringContainsString("hint: value of type 'vector' is not a function", $report);
    }

    public function test_writes_the_user_facing_trace_after_the_error_location(): void
    {
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/main.phel', 3));

        $printer = $this->createStub(ExceptionPrinterInterface::class);
        $printer->method('getUserFacingTraceString')
            ->willReturn("#0 /proj/src/main.phel:6 : (app\\main\\level3 1)\n   ... 4 internal frames\n");

        $report = $this->createFormatter($extractor, $printer)
            ->format($this->errorAt('boom', '/tmp/__phel_abc.php', 9));

        self::assertStringContainsString(
            "at /proj/src/main.phel:3\n#0 /proj/src/main.phel:6 : (app\\main\\level3 1)\n   ... 4 internal frames",
            $report,
        );
    }

    public function test_stack_trace_flag_reaches_the_shared_filter(): void
    {
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/main.phel', 3));

        $printer = $this->createMock(ExceptionPrinterInterface::class);
        $printer->expects(self::once())
            ->method('getUserFacingTraceString')
            ->with(self::anything(), true)
            ->willReturn("#0 /vendor/symfony/console/Command.php(284): run()\n");

        $report = $this->createFormatter($extractor, $printer)
            ->format($this->errorAt('boom', '/proj/src/main.phel', 3), showInternalFrames: true);

        self::assertStringContainsString('#0 /vendor/symfony/console/Command.php(284)', $report);
    }

    public function test_empty_message_falls_back_to_the_no_message_marker(): void
    {
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/main.phel', 3));

        $report = $this->createFormatter($extractor)->format($this->errorAt('', '/proj/src/main.phel', 3));

        self::assertStringContainsString('*no message*', $report);
    }

    public function test_strips_the_eval_path_php_injects_into_its_own_messages(): void
    {
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/main.phel', 3));

        $message = 'Too few arguments to function Foo::bar(), 0 passed in '
            . "/proj/src/php/Compiler/InMemoryEvaluator.php(26) : eval()'d code on line 3 and exactly 1 expected";

        $report = $this->createFormatter($extractor)->format($this->errorAt($message, '/proj/src/main.phel', 3));

        self::assertStringContainsString('0 passed and exactly 1 expected', $report);
        self::assertStringNotContainsString('InMemoryEvaluator.php', $report);
        self::assertStringNotContainsString("eval()'d code", $report);
    }

    private function createFormatter(
        FilePositionExtractorInterface $filePositionExtractor,
        ?ExceptionPrinterInterface $exceptionPrinter = null,
        ?ExceptionHintResolver $hintResolver = null,
    ): RuntimeErrorReportFormatter {
        return new RuntimeErrorReportFormatter(
            $exceptionPrinter ?? $this->createStub(ExceptionPrinterInterface::class),
            $filePositionExtractor,
            new InternalPathDetector(self::PHEL_SRC_DIR, self::CACHE_DIR),
            $hintResolver ?? new ExceptionHintResolver([]),
            Printer::readable(),
            self::STALE_OUTPUT_HINT,
        );
    }

    /**
     * Feeds the extractor one resolved position per lookup, innermost frame
     * first, then maps any further frame to itself.
     *
     * @param list<FilePosition> $positions
     */
    private function positionsThenIdentity(array $positions): callable
    {
        return static function (string $file, int $line) use (&$positions): FilePosition {
            $position = array_shift($positions);

            return $position instanceof FilePosition ? $position : new FilePosition($file, $line);
        };
    }

    private function errorAt(string $message, string $file, int $line): RuntimeException
    {
        // Use the previous-exception slot, which the formatter prefers when present
        $cause = new class($message, $file, $line) extends RuntimeException {
            public function __construct(string $message, string $file, int $line)
            {
                parent::__construct($message);
                $this->file = $file;
                $this->line = $line;
            }
        };

        return new RuntimeException('wrapper', 0, $cause);
    }
}
