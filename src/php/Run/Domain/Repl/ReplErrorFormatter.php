<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Command\Domain\Exceptions\ExceptionPrinterInterface;
use Phel\Compiler\Domain\Evaluator\Exceptions\EvaluatedCodeException;
use Phel\Run\Domain\Repl\Hint\ReplHintInterface;
use Phel\Shared\ColorStyleInterface;
use Throwable;

use function explode;
use function implode;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_contains;
use function trim;

use const PHP_EOL;

final readonly class ReplErrorFormatter
{
    private const array INTERNAL_FRAME_PATHS = [
        '/phel-lang/src/php/Compiler/',
        '/phel-lang/src/php/Run/',
        '/phel-lang/src/php/Build/',
        '/phel-lang/src/php/Command/',
        '/phel-lang/src/php/Console/',
        '/vendor/symfony/console/',
        '/phel-lang/bin/phel',
    ];

    /**
     * @param list<ReplHintInterface> $hints
     */
    public function __construct(
        private array $hints,
        private ExceptionPrinterInterface $exceptionPrinter,
        private ColorStyleInterface $style,
    ) {}

    public function format(Throwable $e): ReplFormattedError
    {
        $cause = $this->unwrap($e);
        $fullTrace = $this->exceptionPrinter->getStackTraceString($e);

        return new ReplFormattedError(
            $this->buildHeadline($cause),
            $this->lookupHint($cause),
            $this->filterTrace($fullTrace),
            $fullTrace,
        );
    }

    public function render(Throwable $e): string
    {
        $formatted = $this->format($e);
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

    private function lookupHint(Throwable $e): ?string
    {
        foreach ($this->hints as $hint) {
            if ($hint->appliesTo($e)) {
                return $hint->hint($e);
            }
        }

        return null;
    }

    private function filterTrace(string $trace): string
    {
        $lines = explode(PHP_EOL, $trace);
        $kept = [];
        $dropped = 0;
        $sawFrame = false;
        $keepingCurrentFrame = false;

        foreach ($lines as $line) {
            $isFrame = preg_match('/^#\d+\s/', $line) === 1;

            if (!$sawFrame && !$isFrame) {
                continue;
            }

            if ($isFrame) {
                $sawFrame = true;

                if ($this->isInternalFrame($line)) {
                    ++$dropped;
                    $keepingCurrentFrame = false;
                    continue;
                }

                $keepingCurrentFrame = true;
                $kept[] = $line;
                continue;
            }

            if ($keepingCurrentFrame) {
                $kept[] = $line;
            }
        }

        if ($dropped > 0) {
            $kept[] = sprintf('  ... %d internal frame%s hidden', $dropped, $dropped === 1 ? '' : 's');
        }

        return implode(PHP_EOL, $kept);
    }

    private function isInternalFrame(string $line): bool
    {
        return array_any(self::INTERNAL_FRAME_PATHS, static fn(string $needle): bool => str_contains($line, $needle));
    }

    private function shortClassName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
