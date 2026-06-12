<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Application;

use Phel\Command\Application\CommandExceptionWriter;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionPrinterInterface;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Command\Domain\Exceptions\Extractor\ReadModel\FilePosition;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

final class CommandExceptionWriterTest extends TestCase
{
    public function test_resolves_compiled_php_to_phel_source_when_source_map_exists(): void
    {
        $compiledPath = '/proj/out/phel/http.php';
        $compiledLine = 387;

        $extractor = $this->createMock(FilePositionExtractorInterface::class);
        $extractor->expects(self::once())
            ->method('getOriginal')
            ->with($compiledPath, $compiledLine)
            ->willReturn(new FilePosition('/proj/src/phel/http.phel', 142));

        $writer = new CommandExceptionWriter(
            $this->createStub(ExceptionPrinterInterface::class),
            $this->createStub(ErrorLogInterface::class),
            $extractor,
            'stale compiled output? try `rm -rf out /var/state/cache` and rebuild.',
        );

        $output = new BufferedOutput();
        $writer->writeStackTrace($output, $this->errorAt('Value of type null is not callable', $compiledPath, $compiledLine));

        $text = $output->fetch();

        self::assertStringContainsString('Value of type null is not callable', $text);
        self::assertStringContainsString('at /proj/src/phel/http.phel:142', $text);
        self::assertStringContainsString('(compiled: /proj/out/phel/http.php:387)', $text);
        self::assertStringNotContainsString('KeepGeneratedTempFiles', $text);
    }

    public function test_emits_stale_output_hint_when_source_map_missing(): void
    {
        $compiledPath = '/proj/out/phel/http.php';
        $compiledLine = 12;

        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        // Same filename back => no source map resolution
        $extractor->method('getOriginal')->willReturn(new FilePosition($compiledPath, $compiledLine));

        $writer = new CommandExceptionWriter(
            $this->createStub(ExceptionPrinterInterface::class),
            $this->createStub(ErrorLogInterface::class),
            $extractor,
            'stale compiled output? try `rm -rf out /var/state/cache` and rebuild.',
        );

        $output = new BufferedOutput();
        $writer->writeStackTrace($output, $this->errorAt('boom', $compiledPath, $compiledLine));

        $text = $output->fetch();

        self::assertStringContainsString('boom', $text);
        self::assertStringContainsString('at /proj/out/phel/http.php:12', $text);
        self::assertStringContainsString('rm -rf out /var/state/cache', $text);
    }

    public function test_internal_phel_lang_source_does_not_invoke_extractor(): void
    {
        $extractor = $this->createMock(FilePositionExtractorInterface::class);
        $extractor->expects(self::never())->method('getOriginal');

        $writer = new CommandExceptionWriter(
            $this->createStub(ExceptionPrinterInterface::class),
            $this->createStub(ErrorLogInterface::class),
            $extractor,
            'stale compiled output? try `rm -rf out /var/state/cache` and rebuild.',
        );

        $output = new BufferedOutput();
        $writer->writeStackTrace(
            $output,
            $this->errorAt('internal', '/home/dev/phel-lang/src/php/Run/Foo.php', 99),
        );

        self::assertSame("internal\n", $output->fetch());
    }

    public function test_writes_user_facing_trace_after_error_location(): void
    {
        $extractor = $this->createStub(FilePositionExtractorInterface::class);
        $extractor->method('getOriginal')->willReturn(new FilePosition('/proj/src/main.phel', 3));

        $printer = $this->createStub(ExceptionPrinterInterface::class);
        $printer->method('getUserFacingTraceString')
            ->willReturn("#0 /proj/src/main.phel:6 : (app\\main\\level3 1)\n   ... 4 internal frames\n");

        $writer = new CommandExceptionWriter(
            $printer,
            $this->createStub(ErrorLogInterface::class),
            $extractor,
            'stale hint',
        );

        $output = new BufferedOutput();
        $writer->writeStackTrace($output, $this->errorAt('boom', '/tmp/__phel_abc.php', 9));

        $text = $output->fetch();

        self::assertStringContainsString('#0 /proj/src/main.phel:6 : (app\\main\\level3 1)', $text);
        self::assertStringContainsString('... 4 internal frames', $text);
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
