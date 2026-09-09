<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Application;

use Phel\Command\Application\CommandExceptionWriter;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Command\Domain\Exceptions\Extractor\ReadModel\FilePosition;
use Phel\Command\Domain\Exceptions\InternalPathDetector;
use Phel\Lang\ExceptionInfo;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\TypeFactory;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Exceptions\Hint\NotCallableHint;
use Phel\Shared\Exceptions\Hint\UndefinedSymbolHint;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Phel\Shared\Printer\Printer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

final class CommandExceptionWriterTest extends TestCase
{
    private const string PHEL_SRC_DIR = '/proj/vendor/phel-lang/phel-lang/src';

    private const string CACHE_DIR = '/proj/.phel/cache';

    public function test_resolves_compiled_php_to_phel_source_when_source_map_exists(): void
    {
        $compiledPath = '/proj/out/phel/http.php';
        $compiledLine = 387;

        $extractor = $this->createMock(FilePositionExtractorInterface::class);
        $extractor->expects(self::once())
            ->method('getOriginal')
            ->with($compiledPath, $compiledLine)
            ->willReturn(new FilePosition('/proj/src/phel/http.phel', 142));

        $writer = $this->createWriter($extractor);

        $output = new BufferedOutput();
        $writer->writeStackTrace($output, $this->errorAt('Value of type null is not callable', $compiledPath, $compiledLine));

        $text = $output->fetch();

        self::assertStringContainsString('Value of type null is not callable', $text);
        self::assertStringContainsString('at /proj/src/phel/http.phel:142', $text);
        self::assertStringContainsString('(compiled: /proj/out/phel/http.php:387)', $text);
        self::assertStringNotContainsString('KeepGeneratedTempFiles', $text);
    }

    public function test_suppresses_compiled_temp_path_for_ephemeral_eval_file(): void
    {
        // An eval temp file (`__phel_<hash>.php`) is an internal artifact; only
        // the resolved Phel source should be shown, never the temp path (#2638).
        $compiledPath = '/private/var/folders/T/phel/tmp/__phel_abc123.php';
        $compiledLine = 4;

        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/03-not-fn.phel', 3));

        $writer = $this->createWriter($extractor);

        $output = new BufferedOutput();
        $writer->writeStackTrace($output, $this->errorAt('Value of type int is not callable', $compiledPath, $compiledLine));

        $text = $output->fetch();

        self::assertStringContainsString('Value of type int is not callable', $text);
        self::assertStringContainsString('at /proj/03-not-fn.phel:3', $text);
        self::assertStringNotContainsString('compiled:', $text);
        self::assertStringNotContainsString('__phel_abc123.php', $text);
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

        $writer = $this->createWriter($extractor);

        $output = new BufferedOutput();
        $writer->writeStackTrace($output, $this->errorAt(
            'Vector index 5 out of bounds',
            self::CACHE_DIR . '/compiled/phel.core__e0b15747.php',
            405,
        ));

        $text = $output->fetch();

        self::assertStringContainsString('at /proj/src/app/main.phel:2', $text);
        self::assertStringNotContainsString('sequences.phel', $text);
        // A compiled path pairing the user's position with the core throw site
        // would name the cache artifact here.
        self::assertStringNotContainsString(self::CACHE_DIR, $text);
    }

    public function test_never_names_the_compiled_cache_in_user_facing_output(): void
    {
        // No user frame follows the core throw site, so the `at` line falls
        // back to it, still without naming the regenerable artifact (#3260).
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturnCallback($this->positionsThenIdentity([
            new FilePosition(self::PHEL_SRC_DIR . '/phel/core/sequences.phel', 220),
        ]));

        $writer = $this->createWriter($extractor);

        $output = new BufferedOutput();
        $writer->writeStackTrace($output, $this->errorAt(
            'Vector index 5 out of bounds',
            self::CACHE_DIR . '/compiled/phel.core__e0b15747.php',
            405,
        ));

        $text = $output->fetch();

        self::assertStringContainsString('at ' . self::PHEL_SRC_DIR . '/phel/core/sequences.phel:220', $text);
        self::assertStringNotContainsString(self::CACHE_DIR, $text);
        self::assertStringNotContainsString('compiled:', $text);
    }

    public function test_prints_the_data_map_of_an_uncaught_ex_info(): void
    {
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/app/main.phel', 2));

        $writer = $this->createWriter($extractor);

        $data = TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('user-id'), 42);

        $output = new BufferedOutput();
        $writer->writeStackTrace($output, new RuntimeException('wrapper', 0, new ExceptionInfo('boom', $data)));

        $text = $output->fetch();

        self::assertStringContainsString('boom', $text);
        self::assertStringContainsString('data: {:user-id 42}', $text);
    }

    public function test_omits_the_data_line_for_an_ex_info_without_data(): void
    {
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/app/main.phel', 2));

        $writer = $this->createWriter($extractor);

        $emptyData = TypeFactory::getInstance()->persistentMapFromArray();

        $output = new BufferedOutput();
        $writer->writeStackTrace($output, new RuntimeException('wrapper', 0, new ExceptionInfo('boom', $emptyData)));

        self::assertStringNotContainsString('data:', $output->fetch());
    }

    public function test_emits_stale_output_hint_when_source_map_missing(): void
    {
        $compiledPath = '/proj/out/phel/http.php';
        $compiledLine = 12;

        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        // Same filename back => no source map resolution
        $extractor->method('getOriginal')->willReturn(new FilePosition($compiledPath, $compiledLine));

        $writer = $this->createWriter($extractor);

        $output = new BufferedOutput();
        $writer->writeStackTrace($output, $this->errorAt('boom', $compiledPath, $compiledLine));

        $text = $output->fetch();

        self::assertStringContainsString('boom', $text);
        self::assertStringContainsString('at /proj/out/phel/http.php:12', $text);
        self::assertStringContainsString('rm -rf out /var/state/cache', $text);
    }

    public function test_runtime_lib_error_skips_located_header_but_keeps_phel_trace(): void
    {
        // A throw from inside the runtime lib has no user source for the `at`
        // header, so the extractor is not consulted for it — yet the Phel call
        // sites must still be surfaced via the filtered trace.
        $extractor = $this->createMock(FilePositionExtractorInterface::class);
        $extractor->expects(self::never())->method('getOriginal');

        $printer = $this->createStub(ExceptionPrinterInterface::class);
        $printer->method('getUserFacingTraceString')
            ->willReturn("#3 /proj/src/main.phel:3 : (phel\\core\\+ 1 \"boom\")\n   ... 2 internal frames\n");

        $writer = $this->createWriter($extractor, $printer);

        $output = new BufferedOutput();
        $writer->writeStackTrace(
            $output,
            $this->errorAt('Expected a number, got string', '/home/dev/phel-lang/src/php/Lang/NumericCoercion.php', 40),
        );

        $text = $output->fetch();

        self::assertStringContainsString('Expected a number, got string', $text);
        self::assertStringContainsString('#3 /proj/src/main.phel:3 : (phel\\core\\+ 1 "boom")', $text);
        self::assertStringContainsString('... 2 internal frames', $text);
        // No misleading `at <internal .php>` header pointing into the runtime lib.
        self::assertStringNotContainsString('at /home/dev/phel-lang/src', $text);
        self::assertStringNotContainsString('rm -rf out', $text);
    }

    public function test_writes_user_facing_trace_after_error_location(): void
    {
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/main.phel', 3));

        $printer = $this->createStub(ExceptionPrinterInterface::class);
        $printer->method('getUserFacingTraceString')
            ->willReturn("#0 /proj/src/main.phel:6 : (app\\main\\level3 1)\n   ... 4 internal frames\n");

        $writer = $this->createWriter($extractor, $printer);

        $output = new BufferedOutput();
        $writer->writeStackTrace($output, $this->errorAt('boom', '/tmp/__phel_abc.php', 9));

        $text = $output->fetch();

        self::assertStringContainsString('#0 /proj/src/main.phel:6 : (app\\main\\level3 1)', $text);
        self::assertStringContainsString('... 4 internal frames', $text);
    }

    public function test_located_exception_emits_hint_for_unresolved_symbol(): void
    {
        $printer = $this->createStub(ExceptionPrinterInterface::class);
        $printer->method('getExceptionString')->willReturn("Cannot resolve symbol 'foo'");

        $located = new class("Cannot resolve symbol 'foo'") extends AbstractLocatedException {};

        $writer = $this->createWriter(
            $this->createStub(FilePositionExtractorInterface::class),
            $printer,
            new ExceptionHintResolver([new UndefinedSymbolHint()]),
        );

        $loc = new SourceLocation('foo.phel', 1, 1);
        $output = new BufferedOutput();
        $writer->writeLocatedException($output, $located, new CodeSnippet($loc, $loc, '(foo)'));

        $text = $output->fetch();

        self::assertStringContainsString("hint: 'foo' is not defined", $text);
    }

    public function test_emits_actionable_hint_for_known_error(): void
    {
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/main.phel', 3));

        $writer = $this->createWriter(
            $extractor,
            null,
            new ExceptionHintResolver([new NotCallableHint()]),
        );

        $output = new BufferedOutput();
        $writer->writeStackTrace(
            $output,
            $this->errorAt(
                'Object of type Phel\\Lang\\Collections\\Vector\\PersistentVector is not callable',
                '/proj/src/main.phel',
                3,
            ),
        );

        $text = $output->fetch();

        self::assertStringContainsString('hint:', $text);
        self::assertStringContainsString("'vector' is not a function", $text);
    }

    private function createWriter(
        FilePositionExtractorInterface $filePositionExtractor,
        ?ExceptionPrinterInterface $exceptionPrinter = null,
        ?ExceptionHintResolver $hintResolver = null,
    ): CommandExceptionWriter {
        return new CommandExceptionWriter(
            $exceptionPrinter ?? $this->createStub(ExceptionPrinterInterface::class),
            $this->createStub(ErrorLogInterface::class),
            $filePositionExtractor,
            'stale compiled output? try `rm -rf out /var/state/cache` and rebuild.',
            $hintResolver ?? new ExceptionHintResolver([]),
            new InternalPathDetector(self::PHEL_SRC_DIR, self::CACHE_DIR),
            Printer::readable(),
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
        // Use the previous-exception slot, which writer prefers when present
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
