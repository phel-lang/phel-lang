<?php

declare(strict_types=1);

namespace PhelTest\Support;

use Phel\Command\Application\TextExceptionPrinter;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinterInterface;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Shared\ColorStyleInterface;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\MungeInterface;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use PHPUnit\Framework\TestCase;

/**
 * Renders a located error through the real printer, with the color style
 * stubbed to identity so a test can pin the plain report a user reads.
 *
 * @psalm-require-extends TestCase
 *
 * @phpstan-require-extends TestCase
 */
trait RendersExceptionReportTrait
{
    private function exceptionReport(AbstractLocatedException $e, CodeSnippet $codeSnippet): string
    {
        $colorStyle = $this->createStub(ColorStyleInterface::class);
        $colorStyle->method('blue')->willReturnCallback(static fn(string $msg): string => $msg);
        $colorStyle->method('red')->willReturnCallback(static fn(string $msg): string => $msg);

        $exceptionPrinter = new TextExceptionPrinter(
            $this->createStub(ExceptionArgsPrinterInterface::class),
            $colorStyle,
            $this->createStub(MungeInterface::class),
            $this->createStub(FilePositionExtractorInterface::class),
            $this->createStub(ErrorLogInterface::class),
            '--stack-trace to show, full trace in .phel/error.log',
        );

        return $exceptionPrinter->getExceptionString($e, $codeSnippet);
    }
}
