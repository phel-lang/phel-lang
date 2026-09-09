<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Application;

use Phel\Command\Application\CommandExceptionWriter;
use Phel\Command\Application\RuntimeErrorReportFormatter;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Command\Domain\Exceptions\Extractor\ReadModel\FilePosition;
use Phel\Command\Domain\Exceptions\InternalPathDetector;
use Phel\Lang\SourceLocation;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Phel\Shared\Exceptions\Hint\UndefinedSymbolHint;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Phel\Shared\Printer\Printer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

final class CommandExceptionWriterTest extends TestCase
{
    public function test_writes_the_shared_runtime_error_report(): void
    {
        $printer = $this->createStub(ExceptionPrinterInterface::class);
        $printer->method('getUserFacingTraceString')
            ->willReturn("#0 /proj/src/main.phel:6 : (app\\main\\level3 1)\n");

        $output = new BufferedOutput();
        $this->createWriter($printer)->writeStackTrace($output, $this->errorAt('boom', '/proj/src/main.phel', 3));

        self::assertSame(
            "boom\n  at /proj/src/main.phel:3\n#0 /proj/src/main.phel:6 : (app\\main\\level3 1)\n",
            $output->fetch(),
        );
    }

    public function test_stack_trace_flag_reaches_the_report(): void
    {
        $printer = $this->createMock(ExceptionPrinterInterface::class);
        $printer->expects(self::once())
            ->method('getUserFacingTraceString')
            ->with(self::anything(), true)
            ->willReturn("#0 /vendor/symfony/console/Command.php(284): run()\n");

        $output = new BufferedOutput();
        $this->createWriter($printer)->writeStackTrace(
            $output,
            $this->errorAt('boom', '/proj/src/main.phel', 3),
            showInternalFrames: true,
        );

        self::assertStringContainsString('#0 /vendor/symfony/console/Command.php(284)', $output->fetch());
    }

    public function test_logs_the_full_trace_even_when_nothing_is_written_to_the_console(): void
    {
        $printer = $this->createStub(ExceptionPrinterInterface::class);
        $printer->method('getStackTraceString')->willReturn('FULL TRACE');

        $errorLog = $this->createMock(ErrorLogInterface::class);
        $errorLog->expects(self::once())->method('writeln')->with('FULL TRACE');

        $this->createWriter($printer, errorLog: $errorLog)->logStackTrace(new RuntimeException('boom'));
    }

    public function test_located_exception_emits_hint_for_unresolved_symbol(): void
    {
        $printer = $this->createStub(ExceptionPrinterInterface::class);
        $printer->method('getExceptionString')->willReturn("Cannot resolve symbol 'foo'");

        $located = new class("Cannot resolve symbol 'foo'") extends AbstractLocatedException {};

        $writer = $this->createWriter(
            $printer,
            new ExceptionHintResolver([new UndefinedSymbolHint()]),
        );

        $loc = new SourceLocation('foo.phel', 1, 1);
        $output = new BufferedOutput();
        $writer->writeLocatedException($output, $located, new CodeSnippet($loc, $loc, '(foo)'));

        self::assertStringContainsString("hint: 'foo' is not defined", $output->fetch());
    }

    private function createWriter(
        ExceptionPrinterInterface $exceptionPrinter,
        ?ExceptionHintResolver $hintResolver = null,
        ?ErrorLogInterface $errorLog = null,
    ): CommandExceptionWriter {
        $hintResolver ??= new ExceptionHintResolver([]);

        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/main.phel', 3));

        return new CommandExceptionWriter(
            $exceptionPrinter,
            $errorLog ?? $this->createStub(ErrorLogInterface::class),
            $hintResolver,
            new RuntimeErrorReportFormatter(
                $exceptionPrinter,
                $extractor,
                new InternalPathDetector('/proj/vendor/phel-lang/phel-lang/src', '/proj/.phel/cache'),
                $hintResolver,
                Printer::readable(),
                'stale compiled output? try `rm -rf out /var/state/cache` and rebuild.',
            ),
        );
    }

    private function errorAt(string $message, string $file, int $line): RuntimeException
    {
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
