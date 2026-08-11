<?php

declare(strict_types=1);

namespace Phel\Balance\Application;

use Phel\Balance\Domain\BalanceResult;
use Phel\Balance\Domain\Exception\BalanceSourceException;
use Phel\Balance\Domain\FileCollectorInterface;
use Phel\Balance\Domain\FileIoInterface;
use Phel\Balance\Domain\FileOutcome;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;

/**
 * @internal
 */
final readonly class PathsBalancer
{
    public function __construct(
        private FileCollectorInterface $fileCollector,
        private DelimiterScanner $scanner,
        private DelimiterRepairer $repairer,
        private FileIoInterface $fileIo,
    ) {}

    /**
     * @param list<string> $paths
     *
     * @throws BalanceSourceException when a listed directory cannot be walked
     */
    public function balance(array $paths, bool $fix): BalanceResult
    {
        $outcomes = [];

        foreach ($this->fileCollector->collect($paths) as $path) {
            $outcomes[] = $this->balanceFile($path, $fix);
        }

        return new BalanceResult($outcomes);
    }

    private function balanceFile(string $path, bool $fix): FileOutcome
    {
        try {
            $code = $this->fileIo->read($path);
        } catch (BalanceSourceException $balanceSourceException) {
            return FileOutcome::unrepairable($path, $balanceSourceException->getMessage());
        }

        try {
            $report = $this->scanner->scan($code, $path);
        } catch (LexerValueException $lexerValueException) {
            // An unterminated `#"regex"`, a bare `#` and the removed `#| |#`
            // block comment all fail to lex rather than lexing to something
            // countable, so a lex failure is a real outcome here, not a bug.
            return FileOutcome::unrepairable($path, $lexerValueException->getMessage());
        }

        if ($report->isBalanced()) {
            return FileOutcome::balanced($path, $report);
        }

        if (!$report->isRepairable()) {
            return FileOutcome::unrepairable($path, $report->unrepairableReason() ?? 'cannot be repaired automatically', $report);
        }

        if (!$fix) {
            return FileOutcome::needsRepair($path, $report);
        }

        try {
            $this->fileIo->write($path, $this->repairer->repair($code, $report));
        } catch (BalanceSourceException $balanceSourceException) {
            return FileOutcome::unrepairable($path, $balanceSourceException->getMessage(), $report);
        }

        return FileOutcome::repaired($path, $report);
    }
}
