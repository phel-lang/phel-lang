<?php

declare(strict_types=1);

namespace Phel\Balance\Application;

use Phel\Balance\Domain\BalanceResult;
use Phel\Balance\Domain\Exception\BalanceSourceException;
use Phel\Balance\Domain\FileCollectorInterface;
use Phel\Balance\Domain\FileIoInterface;
use Phel\Balance\Domain\FileOutcome;
use Phel\Balance\Domain\OpenDelimiter;
use Phel\Balance\Domain\RepairStrategy;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;

use function count;

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
        private ?BoundaryRepairer $boundaryRepairer = null,
        private ?UnexpectedCloserRepairer $unexpectedCloserRepairer = null,
        private ?RepairValidator $validator = null,
    ) {}

    /**
     * @param list<string> $paths
     *
     * @throws BalanceSourceException when a listed directory cannot be walked
     */
    public function balance(array $paths, bool $fix, RepairStrategy $strategy = RepairStrategy::Append): BalanceResult
    {
        $outcomes = [];

        foreach ($this->fileCollector->collect($paths) as $path) {
            $outcomes[] = $this->balanceFile($path, $fix, $strategy);
        }

        return new BalanceResult($outcomes);
    }

    private function balanceFile(string $path, bool $fix, RepairStrategy $strategy): FileOutcome
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

        $appendable = $report->isRepairable();
        $boundaryEligible = $report->unclosed !== []
            && $report->unexpectedClosers === []
            && $report->unterminatedStringLine === null
            && $report->danglingPrefixToken === null
            && $report->topLevelFormOffsetAfterUnclosed !== null;
        $deletionEligible = count($report->unexpectedClosers) === 1
            && !$report->unexpectedClosers[0]->openDelimiter instanceof OpenDelimiter
            && $report->unclosed === []
            && $report->unterminatedStringLine === null
            && $report->danglingPrefixToken === null;

        $eligible = match ($strategy) {
            RepairStrategy::Append => $appendable,
            RepairStrategy::Boundary => $boundaryEligible,
            RepairStrategy::DeleteUnexpected => $deletionEligible,
        };

        if (!$eligible) {
            return FileOutcome::unrepairable($path, $report->unrepairableReason() ?? 'cannot be repaired automatically', $report);
        }

        if (!$fix) {
            return FileOutcome::needsRepair($path, $report);
        }

        $candidate = match ($strategy) {
            RepairStrategy::Append => $this->repairer->repair($code, $report),
            RepairStrategy::Boundary => $this->boundaryRepairer?->repair($code, $report),
            RepairStrategy::DeleteUnexpected => $this->unexpectedCloserRepairer?->repair($code, $report),
        };

        if ($candidate === null || ($this->validator instanceof RepairValidator && !$this->validator->isValid($candidate, $path))) {
            return FileOutcome::unrepairable($path, 'candidate repair did not produce valid balanced Phel', $report);
        }

        try {
            $this->fileIo->write($path, $candidate);
        } catch (BalanceSourceException $balanceSourceException) {
            return FileOutcome::unrepairable($path, $balanceSourceException->getMessage(), $report);
        }

        return FileOutcome::repaired($path, $report);
    }
}
