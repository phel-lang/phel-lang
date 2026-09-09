<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;
use Phel\Shared\ColorStyleInterface;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Exceptions\Hint\ExceptionHintResolver;
use Throwable;

use function implode;
use function preg_replace;
use function rtrim;
use function sprintf;
use function trim;

use const PHP_EOL;

/**
 * Shapes the error report a user reads at the prompt: headline, hint, trace.
 *
 * The trace itself comes from {@see ExceptionPrinterInterface::getUserFacingTraceString()},
 * the single filter `phel run` uses too. This class used to keep its own copy,
 * a denylist of source paths that only matched a `vendor/phel-lang/phel-lang`
 * layout and let every other checkout's compiler frames through.
 *
 * @internal
 */
final readonly class ReplErrorFormatter
{
    public function __construct(
        private ExceptionHintResolver $hintResolver,
        private ExceptionPrinterInterface $exceptionPrinter,
        private ColorStyleInterface $style,
    ) {}

    public function format(Throwable $e, bool $showInternalFrames = false): ReplFormattedError
    {
        $cause = $this->unwrap($e);

        return new ReplFormattedError(
            $this->buildHeadline($cause),
            $this->hintResolver->hintFor($cause),
            rtrim($this->exceptionPrinter->getUserFacingTraceString($cause, $showInternalFrames)),
        );
    }

    public function render(Throwable $e, bool $showInternalFrames = false): string
    {
        $formatted = $this->format($e, $showInternalFrames);
        $parts = [$formatted->headline];

        if ($formatted->hint !== null) {
            $parts[] = $this->style->yellow('hint: ' . $formatted->hint);
        }

        if ($formatted->trace !== '') {
            $parts[] = '';
            $parts[] = $formatted->trace;
        }

        return implode(PHP_EOL, $parts);
    }

    private function unwrap(Throwable $e): Throwable
    {
        if ($e instanceof EvaluatedCodeException) {
            return $e->getOriginalException();
        }

        return $e;
    }

    private function buildHeadline(Throwable $e): string
    {
        $type = $this->shortClassName($e::class);
        $message = $this->cleanMessage($e->getMessage());

        return $this->style->red(sprintf('%s: %s', $type, $message));
    }

    private function cleanMessage(string $message): string
    {
        if ($message === '') {
            return '*no message*';
        }

        $cleaned = preg_replace(
            '/ in [^\s]+\(\d+\)\s*:\s*eval\(\)\'d code on line \d+/',
            '',
            $message,
        );

        return trim($cleaned ?? $message);
    }

    private function shortClassName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
