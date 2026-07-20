<?php

declare(strict_types=1);

namespace Phel\Command\Application;

use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinterInterface;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;
use Phel\Lang\FnInterface;
use Phel\Lang\SourceLocation;
use Phel\Shared\ColorStyleInterface;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\MungeInterface;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use ReflectionClass;
use Throwable;

use function is_string;
use function sprintf;
use function strlen;

use const PHP_EOL;

/**
 * Renders Phel exceptions and stack traces as styled terminal text, with blue
 * message headers, the offending code snippet, and a red caret pointer under the
 * exact error span. Compiled PHP locations are mapped back to their Phel source
 * via the {@see FilePositionExtractorInterface}.
 */
final readonly class TextExceptionPrinter implements ExceptionPrinterInterface
{
    public function __construct(
        private ExceptionArgsPrinterInterface $exceptionArgsPrinter,
        private ColorStyleInterface $style,
        private MungeInterface $munge,
        private FilePositionExtractorInterface $filePositionExtractor,
        private ErrorLogInterface $errorLog,
    ) {}

    public function printError(string $error): void
    {
        echo $error . PHP_EOL;
        $this->errorLog->writeln($error);
    }

    public function printException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void
    {
        $this->errorLog->writeln($this->getExceptionString($e, $codeSnippet));
    }

    /**
     * Builds the full error string: a styled message header, the source location,
     * and each line of the code snippet prefixed with its absolute line number.
     *
     * The loop maps a snippet-relative offset (`$index`) to an absolute source line
     * by adding the snippet's first line. A caret pointer is appended only when the
     * error spans a single line and that line matches the one currently being
     * printed; multi-line errors are not underlined.
     */
    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string
    {
        $str = '';
        $errorStartLocation = $e->getStartLocation() ?? $codeSnippet->getStartLocation();
        $errorEndLocation = $e->getEndLocation() ?? $codeSnippet->getEndLocation();
        $errorFirstLine = $errorStartLocation->getLine();
        $codeFirstLine = $codeSnippet->getStartLocation()->getLine();

        $errorCode = $e->getErrorCode();
        $errorPrefix = $errorCode instanceof ErrorCode ? sprintf('[%s] ', $errorCode->value) : '';

        $str .= $this->style->blue($errorPrefix . $e->getMessage()) . PHP_EOL;
        $str .= 'in ' . $errorStartLocation->getFile() . ':' . $errorFirstLine . PHP_EOL . PHP_EOL;

        $lines = explode(PHP_EOL, $codeSnippet->getCode());
        $endLineLength = strlen((string) $codeSnippet->getEndLocation()->getLine());
        $padLength = $endLineLength - strlen((string) $codeFirstLine);

        foreach ($lines as $index => $line) {
            $str .= str_pad((string) ($codeFirstLine + $index), $padLength, ' ', STR_PAD_LEFT);
            if ($line !== '') {
                $str .= '| ' . $line . PHP_EOL;
            } else {
                $str .= '|' . PHP_EOL;
            }

            $eStartLine = $errorStartLocation->getLine();
            if ($eStartLine !== $errorEndLocation->getLine()) {
                continue;
            }

            if ($eStartLine !== $index + $codeSnippet->getStartLocation()->getLine()) {
                continue;
            }

            $str .= $this->underliningErrorPointer($endLineLength, $errorStartLocation, $errorEndLocation);
        }

        return $str;
    }

    public function printStackTrace(Throwable $e): void
    {
        $trace = $this->getStackTraceString($e);
        echo $trace . PHP_EOL;
        $this->errorLog->writeln($trace);
    }

    public function getStackTraceString(Throwable $e): string
    {
        $str = '';

        if ($e instanceof EvaluatedCodeException) {
            $original = $e->getOriginalException();
            $type = $original::class;
            $msg = $original->getMessage();
            $errorFile = $original->getFile();
            $errorLine = $original->getLine();
            $phelFile = $e->getPhelFile();
            $phelLine = $e->getPhelLine();

            $str .= $this->style->blue(sprintf('%s: %s', $type, $msg) . PHP_EOL);
            $str .= sprintf('in %s:%d (gen: %s:%d)', $phelFile, $phelLine, $errorFile, $errorLine) . PHP_EOL . PHP_EOL;

            return $str . $this->renderTrace($original);
        }

        $type = $e::class;
        $msg = $e->getPrevious()?->getMessage() ?? $e->getMessage();
        $errorFile = $e->getPrevious()?->getFile() ?? $e->getFile();
        $errorLine = $e->getPrevious()?->getLine() ?? $e->getLine();
        $pos = $this->filePositionExtractor->getOriginal($errorFile, $errorLine);

        $str .= $this->style->blue(sprintf('%s: %s', $type, $msg) . PHP_EOL);
        $str .= sprintf('in %s:%d (gen: %s:%d)', $pos->filename(), $pos->line(), $errorFile, $errorLine) . PHP_EOL . PHP_EOL;

        return $str . $this->renderTrace($e);
    }

    /**
     * Renders only the frames that originate in Phel code, each mapped back to
     * its `.phel` source location. Runs of PHP-native frames (vendor, runtime
     * internals) are collapsed into a dimmed `... N internal frame(s)` marker;
     * the full unfiltered trace is always available in the error log.
     */
    public function getUserFacingTraceString(Throwable $e): string
    {
        $str = '';
        $hidden = 0;

        foreach ($e->getTrace() as $i => $frame) {
            $fnName = $this->phelFnName($frame['class'] ?? null);

            if ($fnName === null) {
                ++$hidden;
                continue;
            }

            $str .= $this->hiddenFramesMarker($hidden);
            $hidden = 0;

            $file = $frame['file'] ?? 'unknown_file';
            $line = $frame['line'] ?? 0;
            $argString = $this->exceptionArgsPrinter->parseArgsAsString($frame['args'] ?? []);
            $pos = $this->filePositionExtractor->getOriginal($file, $line);
            $str .= sprintf('#%d %s:%d : (%s%s)', $i, $pos->filename(), $pos->line(), $fnName, $argString) . PHP_EOL;
        }

        return $str . $this->hiddenFramesMarker($hidden);
    }

    private function hiddenFramesMarker(int $hidden): string
    {
        if ($hidden === 0) {
            return '';
        }

        return sprintf('   ... %d internal frame%s', $hidden, $hidden === 1 ? '' : 's') . PHP_EOL;
    }

    /**
     * Returns the decoded Phel function name when the frame's class is a
     * compiled Phel fn, or null for PHP-native frames.
     */
    private function phelFnName(?string $class): ?string
    {
        if ($class === null || !class_exists($class)) {
            return null;
        }

        $rf = new ReflectionClass($class);
        if (!$rf->implementsInterface(FnInterface::class)) {
            return null;
        }

        if (!$rf->hasConstant('BOUND_TO')) {
            return '__invoke';
        }

        $boundTo = $rf->getConstant('BOUND_TO');

        return is_string($boundTo) ? $this->munge->decodeNs($boundTo) : '__invoke';
    }

    private function renderTrace(Throwable $e): string
    {
        $str = '';

        foreach ($e->getTrace() as $i => $frame) {
            $file = $frame['file'] ?? 'unknown_file';
            $line = $frame['line'] ?? 0;

            $fnName = $this->phelFnName($frame['class'] ?? null);
            if ($fnName !== null) {
                $argString = $this->exceptionArgsPrinter->parseArgsAsString($frame['args'] ?? []);
                $pos = $this->filePositionExtractor->getOriginal($file, $line);
                $str .= sprintf('#%d %s:%d (gen: %s:%d) : (%s%s)', $i, $pos->filename(), $pos->line(), $file, $line, $fnName, $argString) . PHP_EOL;

                continue;
            }

            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $fn = $frame['function'] ?? ''; // @phpstan-ignore-line
            $argString = $this->exceptionArgsPrinter->buildPhpArgsString($frame['args'] ?? []);
            $str .= sprintf('#%d %s(%d): %s%s%s(%s)', $i, $file, $line, $class, $type, $fn, $argString) . PHP_EOL;
        }

        return $str;
    }

    private function underliningErrorPointer(int $lineLength, SourceLocation $start, SourceLocation $end): string
    {
        $preEmptyLines = str_repeat(' ', $lineLength + 2 + $start->getColumn());
        $pointer = str_repeat('^', max(1, $end->getColumn() - $start->getColumn()));
        $pointerInRed = $this->style->red($pointer);

        return $preEmptyLines . $pointerInRed . PHP_EOL;
    }
}
