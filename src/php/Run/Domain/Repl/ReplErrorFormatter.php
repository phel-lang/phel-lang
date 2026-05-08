<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

use Phel\Command\Domain\Exceptions\ExceptionPrinterInterface;
use Phel\Run\Domain\Repl\Hint\ReplHintInterface;
use Phel\Shared\ColorStyleInterface;
use Throwable;

use function explode;
use function implode;
use function preg_match;
use function sprintf;
use function str_contains;

use const PHP_EOL;

final readonly class ReplErrorFormatter
{
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
        $fullTrace = $this->exceptionPrinter->getStackTraceString($e);

        return new ReplFormattedError(
            $this->buildHeadline($e),
            $this->lookupHint($e),
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

    private function buildHeadline(Throwable $e): string
    {
        $type = $this->shortClassName($e::class);
        $message = $e->getMessage() !== '' ? $e->getMessage() : '*no message*';

        return $this->style->red(sprintf('%s: %s', $type, $message));
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

        foreach ($lines as $line) {
            if ($this->isInternalFrame($line)) {
                ++$dropped;
                continue;
            }

            $kept[] = $line;
        }

        if ($dropped > 0) {
            $kept[] = sprintf('  ... %d internal frame%s hidden', $dropped, $dropped === 1 ? '' : 's');
        }

        return implode(PHP_EOL, $kept);
    }

    private function isInternalFrame(string $line): bool
    {
        if (preg_match('/^#\d+\s/', $line) !== 1) {
            return false;
        }

        return str_contains($line, '/phel-lang/src/php/Compiler/')
            || str_contains($line, '/phel-lang/src/php/Run/')
            || str_contains($line, '/phel-lang/src/php/Build/')
            || str_contains($line, '/phel-lang/src/php/Command/');
    }

    private function shortClassName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
