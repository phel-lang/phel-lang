<?php

declare(strict_types=1);

namespace Phel\Balance\Application;

use Phel\Balance\Domain\BalanceReport;
use Phel\Balance\Domain\RepairCandidate;
use Phel\Balance\Domain\RepairEdit;
use Phel\Balance\Domain\RepairPlan;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Parser\Node\Token;

use Throwable;

use function array_key_last;
use function array_merge;
use function count;
use function max;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;
use function usort;

/**
 * Bounded repair search.
 *
 * Re-lexes the file and walks the delimiter token stream, branching at every
 * closing delimiter that needs reconciliation. Each branch is one edit (insert,
 * replace or delete); branches are pruned past a three-edit cap. Every fully
 * reconciled branch is re-lexed, parsed and re-scanned; only candidates that
 * come back valid and balanced survive. The unique cheapest survivor wins; a
 * tie at the cheapest cost is refused rather than guessed.
 *
 * @phpstan-type ScannedToken array{type: int, code: string, line: int, offset: int}
 * @phpstan-type StackLevel array{openerText: string, closerText: string, line: int, offset: int, lastChildCloseLine: int|null}
 *
 * @internal
 */
final readonly class RepairSearch
{
    /** @var array<int, string> */
    private const array CLOSER_TEXT_FOR_OPENER = [
        Token::T_OPEN_PARENTHESIS => ')',
        Token::T_HASH_FN => ')',
        Token::T_READER_COND => ')',
        Token::T_READER_COND_SPLICING => ')',
        Token::T_OPEN_BRACKET => ']',
        Token::T_OPEN_BRACE => '}',
        Token::T_HASH_OPEN_BRACE => '}',
    ];

    private const array TEXT_FOR_CLOSER = [
        Token::T_CLOSE_PARENTHESIS => ')',
        Token::T_CLOSE_BRACKET => ']',
        Token::T_CLOSE_BRACE => '}',
    ];

    private const int EDIT_CAP = 3;

    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        private DelimiterScanner $scanner,
        private RepairValidator $validator,
    ) {}

    public function search(string $code, string $source, BalanceReport $report): RepairPlan
    {
        $tokens = $this->collectTokens($code, $source);
        if ($tokens === []) {
            return RepairPlan::refused('no delimiter tokens to search');
        }

        $pre = $this->prePass($tokens);

        $candidates = [];
        $prunedByCap = false;
        $this->dfs(
            $code,
            $tokens,
            $pre,
            $report,
            index: 0,
            stack: [],
            edits: [],
            editCount: 0,
            invalid: false,
            candidates: $candidates,
            prunedByCap: $prunedByCap,
        );

        if ($candidates === []) {
            if ($prunedByCap) {
                return RepairPlan::refused('minimal repair exceeds the 3-edit cap');
            }

            return RepairPlan::refused($report->unrepairableReason() ?? 'no valid repair candidate within the edit cap');
        }

        $valid = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            if (!$candidate->isValid()) {
                continue;
            }
            $hash = $candidate->repaired;
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;
            $valid[] = $candidate;
        }

        if ($valid === []) {
            return RepairPlan::refused('no candidate re-lexed, parsed and re-scanned balanced', $candidates);
        }

        $ranked = RepairPlan::sortByCost($valid);
        $best = $ranked[0];
        $tied = [];
        foreach ($ranked as $candidate) {
            if ($candidate->cost() === $best->cost() && $candidate->editCount() === $best->editCount()) {
                $tied[] = $candidate;
            } else {
                break;
            }
        }

        if (count($tied) > 1) {
            return RepairPlan::refused('tied candidates', $tied);
        }

        return new RepairPlan([$best]);
    }

    /**
     * @param list<ScannedToken>    $tokens
     * @param list<StackLevel>      $stack
     * @param list<RepairEdit>      $edits
     * @param list<RepairCandidate> $candidates
     */
    private function dfs(string $code, array $tokens, PrePass $pre, BalanceReport $report, int $index, array $stack, array $edits, int $editCount, bool $invalid, array &$candidates, bool &$prunedByCap): void
    {
        if ($invalid || $editCount > self::EDIT_CAP) {
            if ($editCount > self::EDIT_CAP) {
                $prunedByCap = true;
            }
            return;
        }

        if ($index >= count($tokens)) {
            $this->finalize($code, $pre, $report, $stack, $edits, $invalid, $candidates, $prunedByCap);
            return;
        }

        $token = $tokens[$index];
        $type = $token['type'];

        // A new top-level form after the outermost unclosed level is the
        // boundary: the missing closers belong before it, so stop walking and
        // finalize here rather than swallowing the boundary form into the open
        // level (which would move its children's close lines past the boundary).
        if ($report->topLevelFormOffsetAfterUnclosed !== null
            && $token['offset'] >= $report->topLevelFormOffsetAfterUnclosed) {
            $this->finalize($code, $pre, $report, $stack, $edits, $invalid, $candidates, $prunedByCap);
            return;
        }

        if (isset(self::CLOSER_TEXT_FOR_OPENER[$type])) {
            $stack[] = [
                'openerText' => $token['code'],
                'closerText' => self::CLOSER_TEXT_FOR_OPENER[$type],
                'line' => $token['line'],
                'offset' => $token['offset'],
                'lastChildCloseLine' => null,
            ];
            $this->dfs($code, $tokens, $pre, $report, $index + 1, $stack, $edits, $editCount, $invalid, $candidates, $prunedByCap);
            return;
        }

        if (!isset(self::TEXT_FOR_CLOSER[$type])) {
            $this->dfs($code, $tokens, $pre, $report, $index + 1, $stack, $edits, $editCount, $invalid, $candidates, $prunedByCap);
            return;
        }

        $ambiguous = $pre->ambiguous[$index] ?? false;

        if ($stack === []) {
            // Surplus closer: the only option is to delete it.
            $this->branchDelete($code, $tokens, $pre, $report, $index, $stack, $edits, $editCount, $invalid, $candidates, $token, $ambiguous, $prunedByCap);
            return;
        }

        $topIndex = array_key_last($stack);
        $top = $stack[$topIndex];
        $closerText = self::TEXT_FOR_CLOSER[$type];

        if ($top['closerText'] === $closerText) {
            // Matched closer: consume, and (when the file already had a surplus
            // closer somewhere) also consider that THIS one is the spurious one.
            $popped = $stack;
            array_pop($popped);
            $popped = $this->withParentChildCloseLine($popped, $token['line']);
            $this->dfs($code, $tokens, $pre, $report, $index + 1, $popped, $edits, $editCount, $invalid, $candidates, $prunedByCap);

            if ($pre->hadSurplus) {
                $this->branchDelete($code, $tokens, $pre, $report, $index, $stack, $edits, $editCount, $invalid, $candidates, $token, $ambiguous, $prunedByCap);
            }
            return;
        }

        // Mismatched closer: insert the expected closer, replace, or delete.
        // Replacing or deleting a mismatched closer with no parent level guesses
        // whether the opener or the closer was the typo, so those branches are
        // invalid for an ambiguous (outermost) mismatch.
        $insertAt = $this->endOfPreviousNonBlankLine($code, $token['offset']);
        $inserted = RepairEdit::insert(
            $insertAt,
            $top['closerText'],
            sprintf("insert '%s' to close '%s' opened on line %d", $top['closerText'], $top['openerText'], $top['line']),
            $this->lineAtOffset($code, $insertAt),
        );
        $afterInsert = $stack;
        array_pop($afterInsert);
        $this->dfs($code, $tokens, $pre, $report, $index, $afterInsert, array_merge($edits, [$inserted]), $editCount + 1, $invalid, $candidates, $prunedByCap);

        $this->branchReplace($code, $tokens, $pre, $report, $index, $stack, $edits, $editCount, $invalid, $candidates, $token, $top, $ambiguous, $prunedByCap);
        $this->branchDelete($code, $tokens, $pre, $report, $index, $stack, $edits, $editCount, $invalid, $candidates, $token, $ambiguous, $prunedByCap);
    }

    /**
     * @param list<StackLevel>      $stack
     * @param list<RepairEdit>      $edits
     * @param list<RepairCandidate> $candidates
     */
    private function finalize(string $code, PrePass $pre, BalanceReport $report, array $stack, array $edits, bool $invalid, array &$candidates, bool &$prunedByCap): void
    {
        if ($invalid) {
            return;
        }

        if ($stack === []) {
            $repaired = $this->applyEdits($code, $edits);
            $candidates[] = $this->buildCandidate($code, $edits, $repaired);
            return;
        }

        $closeEdits = $this->planClosers($code, $report, $stack);

        $all = array_merge($edits, $closeEdits);
        if (count($all) > self::EDIT_CAP) {
            $prunedByCap = true;
            return;
        }

        $repaired = $this->applyEdits($code, $all);
        $candidates[] = $this->buildCandidate($code, $all, $repaired);
    }

    /**
     * @param list<StackLevel> $stack
     *
     * @return list<RepairEdit>
     */
    private function planClosers(string $code, BalanceReport $report, array $stack): array
    {
        if ($report->topLevelFormOffsetAfterUnclosed !== null) {
            return $this->planBoundaryClosers($code, $stack);
        }

        return $this->planAppendClosers($code, $report, $stack);
    }

    /**
     * @param list<StackLevel> $stack
     *
     * @return list<RepairEdit>
     */
    private function planBoundaryClosers(string $code, array $stack): array
    {
        // Innermost to outermost. A bracket or brace level is a single-line
        // literal collection, so it closes at the end of its own opener line; a
        // paren level is a form body that runs to the boundary, so it closes at
        // the latest line any deeper level closed (never earlier than the level
        // it contains). Each missing closer is its own edit, so the edit cap is
        // a count of closers, not of insert positions.
        $innerCloseLine = 0;
        $edits = [];
        for ($i = count($stack) - 1; $i >= 0; --$i) {
            $level = $stack[$i];
            // A bracket or brace level is a single-line literal collection, so
            // it closes at the end of its own opener line; a paren level is a
            // form body that runs to the boundary, so it closes at the latest
            // line any deeper level closed (never earlier than the level it
            // contains). The forms physically nested in an unclosed bracket are
            // really the parent paren's body, so the bracket's own last-child
            // line propagates up.
            $isLiteral = $level['closerText'] !== ')';
            $own = $level['lastChildCloseLine'] ?? $level['line'];
            $closeLine = $isLiteral
                ? $level['line']
                : max($own, $innerCloseLine);
            $edits[] = RepairEdit::insert(
                $this->endOfLineContent($code, $closeLine),
                $level['closerText'],
                sprintf("insert '%s' to close '%s' opened on line %d", $level['closerText'], $level['openerText'], $level['line']),
                $closeLine,
            );
            $innerCloseLine = max($innerCloseLine, $closeLine, $own);
        }

        return $edits;
    }

    /**
     * @param list<StackLevel> $stack
     *
     * @return list<RepairEdit>
     */
    private function planAppendClosers(string $code, BalanceReport $report, array $stack): array
    {
        $closers = '';
        for ($i = count($stack) - 1; $i >= 0; --$i) {
            $closers .= $stack[$i]['closerText'];
        }

        $trailingNewline = str_ends_with($code, "\n") ? "\n" : '';

        if ($report->endsInLineComment) {
            return [RepairEdit::insert(
                strlen(rtrim($code, "\r\n")),
                "\n" . $closers . $trailingNewline,
                'append missing closers after the trailing comment',
                $this->lineAtOffset($code, strlen($code)),
            )];
        }

        return [RepairEdit::insert(
            strlen(rtrim($code)),
            $closers . $trailingNewline,
            'append missing closers at the end of the file',
            $this->lineAtOffset($code, strlen($code)),
        )];
    }

    /**
     * @param list<RepairEdit> $edits
     */
    private function applyEdits(string $code, array $edits): string
    {
        $sorted = $edits;
        usort($sorted, static fn(RepairEdit $a, RepairEdit $b): int => $b->offset <=> $a->offset);

        $applied = $code;
        foreach ($sorted as $edit) {
            $applied = substr($applied, 0, $edit->offset) . $edit->replacement . substr($applied, $edit->offset + $edit->length);
        }

        return $applied;
    }

    /**
     * @param list<RepairEdit> $edits
     */
    private function buildCandidate(string $original, array $edits, string $repaired): RepairCandidate
    {
        $parserValid = $this->validator->isValid($repaired, 'repair-search');
        $reScanBalanced = false;
        if ($parserValid) {
            try {
                $reScanBalanced = $this->scanner->scan($repaired, 'repair-search')->isBalanced();
            } catch (Throwable) {
                $reScanBalanced = false;
            }
        }

        return new RepairCandidate($original, $edits, $repaired, $parserValid, $reScanBalanced);
    }

    /**
     * @param list<ScannedToken>    $tokens
     * @param list<StackLevel>      $stack
     * @param list<RepairEdit>      $edits
     * @param list<RepairCandidate> $candidates
     * @param ScannedToken          $token
     */
    private function branchDelete(string $code, array $tokens, PrePass $pre, BalanceReport $report, int $index, array $stack, array $edits, int $editCount, bool $invalid, array &$candidates, array $token, bool $ambiguous, bool &$prunedByCap): void
    {
        $edit = RepairEdit::delete($token['offset'], strlen($token['code']), sprintf("delete '%s'", $token['code']), $token['line']);
        $this->dfs($code, $tokens, $pre, $report, $index + 1, $stack, array_merge($edits, [$edit]), $editCount + 1, $invalid || $ambiguous, $candidates, $prunedByCap);
    }

    /**
     * @param list<ScannedToken>    $tokens
     * @param list<StackLevel>      $stack
     * @param list<RepairEdit>      $edits
     * @param list<RepairCandidate> $candidates
     * @param ScannedToken          $token
     * @param StackLevel            $top
     */
    private function branchReplace(string $code, array $tokens, PrePass $pre, BalanceReport $report, int $index, array $stack, array $edits, int $editCount, bool $invalid, array &$candidates, array $token, array $top, bool $ambiguous, bool &$prunedByCap): void
    {
        $edit = RepairEdit::replace(
            $token['offset'],
            strlen($token['code']),
            $top['closerText'],
            sprintf("replace '%s' with '%s'", $token['code'], $top['closerText']),
            $token['line'],
        );
        $popped = $stack;
        array_pop($popped);
        $popped = $this->withParentChildCloseLine($popped, $token['line']);
        $this->dfs($code, $tokens, $pre, $report, $index + 1, $popped, array_merge($edits, [$edit]), $editCount + 1, $invalid || $ambiguous, $candidates, $prunedByCap);
    }

    /**
     * Returns a copy of `$stack` with the outermost level's `lastChildCloseLine`
     * set to `$line`. Rebuilds the element as a full-shape array so the type is
     * preserved for the caller.
     *
     * @param list<StackLevel> $stack
     *
     * @return list<StackLevel>
     */
    private function withParentChildCloseLine(array $stack, int $line): array
    {
        if ($stack === []) {
            return $stack;
        }

        $index = array_key_last($stack);
        $level = $stack[$index];
        $stack[$index] = [
            'openerText' => $level['openerText'],
            'closerText' => $level['closerText'],
            'line' => $level['line'],
            'offset' => $level['offset'],
            'lastChildCloseLine' => $line,
        ];

        return $stack;
    }

    private function endOfPreviousNonBlankLine(string $code, int $offset): int
    {
        $lineStart = strrpos(substr($code, 0, $offset), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $cursor = $lineStart;
        while ($cursor > 0) {
            $lineEnd = $cursor - 1;
            $previousLineStart = strrpos(substr($code, 0, $lineEnd), "\n");
            $previousLineStart = $previousLineStart === false ? 0 : $previousLineStart + 1;
            $line = substr($code, $previousLineStart, max(0, $lineEnd - $previousLineStart));
            if (trim($line) !== '') {
                return $lineEnd > 0 && $code[$lineEnd - 1] === "\r"
                    ? $lineEnd - 1
                    : $lineEnd;
            }
            $cursor = $previousLineStart;
        }

        return $offset;
    }

    private function endOfLineContent(string $code, int $line): int
    {
        $current = 1;
        $pos = 0;
        while ($current < $line && $pos < strlen($code)) {
            $next = strpos($code, "\n", $pos);
            if ($next === false) {
                return strlen($code);
            }
            $pos = $next + 1;
            ++$current;
        }

        $lineEnd = strpos($code, "\n", $pos);
        if ($lineEnd === false) {
            return strlen($code);
        }

        return $lineEnd > 0 && $code[$lineEnd - 1] === "\r"
            ? $lineEnd - 1
            : $lineEnd;
    }

    private function lineAtOffset(string $code, int $offset): int
    {
        $line = 1;
        $len = strlen($code);
        for ($i = 0; $i < $offset && $i < $len; ++$i) {
            if ($code[$i] === "\n") {
                ++$line;
            }
        }

        return $line;
    }

    /**
     * @return list<ScannedToken>
     */
    private function collectTokens(string $code, string $source): array
    {
        $tokens = [];
        $offset = 0;
        foreach ($this->compilerFacade->lexString($code, $source) as $token) {
            $type = $token->getType();
            if ($type === Token::T_EOF) {
                break;
            }
            $tokens[] = [
                'type' => $type,
                'code' => $token->getCode(),
                'line' => $token->getStartLocation()->getLine(),
                'offset' => $offset,
            ];
            $offset += strlen($token->getCode());
        }

        return $tokens;
    }

    /**
     * @param list<ScannedToken> $tokens
     */
    private function prePass(array $tokens): PrePass
    {
        $stack = [];
        $ambiguous = [];
        $hadSurplus = false;
        foreach ($tokens as $i => $token) {
            $type = $token['type'];
            if (isset(self::CLOSER_TEXT_FOR_OPENER[$type])) {
                $stack[] = ['closerText' => self::CLOSER_TEXT_FOR_OPENER[$type]];
                continue;
            }
            if (!isset(self::TEXT_FOR_CLOSER[$type])) {
                continue;
            }
            $closerText = self::TEXT_FOR_CLOSER[$type];
            if ($stack === []) {
                $hadSurplus = true;
                continue;
            }
            $top = $stack[array_key_last($stack)];
            if ($top['closerText'] === $closerText) {
                array_pop($stack);
            } elseif (count($stack) === 1) {
                // A mismatched closer with no parent level: editing it guesses
                // whether the opener or the closer was the typo.
                $ambiguous[$i] = true;
                // Mirror the scanner: do not pop a mismatched level.
            }
        }

        return new PrePass($ambiguous, $hadSurplus);
    }
}
