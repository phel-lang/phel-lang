<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

use function array_reverse;
use function implode;
use function sprintf;

/**
 * What one scan of one file found.
 *
 * @internal
 */
final readonly class BalanceReport
{
    /**
     * @param list<OpenDelimiter>    $unclosed                      outermost first
     * @param list<UnexpectedCloser> $unexpectedClosers
     * @param int|null               $unterminatedStringLine        line of the first unterminated string literal
     * @param int|null               $topLevelFormLineAfterUnclosed line of the first column-0 opener that starts
     *                                                              after the outermost unclosed level
     * @param string|null            $danglingPrefixToken           trailing reader prefix awaiting a datum
     * @param bool                   $endsInLineComment             whether the last real token is a `;` comment
     */
    public function __construct(
        public array $unclosed,
        public array $unexpectedClosers,
        public ?int $unterminatedStringLine,
        public ?int $topLevelFormLineAfterUnclosed,
        public ?string $danglingPrefixToken,
        public bool $endsInLineComment,
    ) {}

    public function isBalanced(): bool
    {
        return $this->unclosed === []
            && $this->unexpectedClosers === []
            && $this->unterminatedStringLine === null;
    }

    /**
     * Repair appends at the end of the file, so it is only ever correct when the
     * missing closers genuinely belong there. Four things say they do not.
     */
    public function isRepairable(): bool
    {
        return $this->unclosed !== []
            && $this->unexpectedClosers === []
            && $this->unterminatedStringLine === null
            && $this->topLevelFormLineAfterUnclosed === null
            && $this->danglingPrefixToken === null;
    }

    /**
     * The closers to append, innermost level first.
     */
    public function missingClosers(): string
    {
        $closers = '';
        foreach (array_reverse($this->unclosed) as $open) {
            $closers .= $open->closerText;
        }

        return $closers;
    }

    public function unrepairableReason(): ?string
    {
        if ($this->unterminatedStringLine !== null) {
            return 'unterminated string literal on line ' . $this->unterminatedStringLine;
        }

        if ($this->unexpectedClosers !== []) {
            return $this->closerMismatchReason();
        }

        if ($this->topLevelFormLineAfterUnclosed !== null && $this->unclosed !== []) {
            return sprintf(
                "unclosed '%s' on line %d, but a new top-level form starts on line %d; the missing '%s' belongs before line %d, not at the end of the file",
                $this->unclosed[0]->openerText,
                $this->unclosed[0]->line,
                $this->topLevelFormLineAfterUnclosed,
                $this->unclosed[0]->closerText,
                $this->topLevelFormLineAfterUnclosed,
            );
        }

        if ($this->danglingPrefixToken !== null) {
            return sprintf(
                "file ends with '%s', which reads the next form; a closer appended there would become that form",
                $this->danglingPrefixToken,
            );
        }

        return null;
    }

    private function closerMismatchReason(): string
    {
        $parts = [];
        foreach ($this->unexpectedClosers as $closer) {
            $parts[] = $closer->openDelimiter instanceof OpenDelimiter
                ? sprintf("line %d: '%s' closes '%s' opened on line %d", $closer->line, $closer->closerText, $closer->openDelimiter->openerText, $closer->openDelimiter->line)
                : sprintf("line %d: '%s' closes nothing", $closer->line, $closer->closerText);
        }

        return implode('; ', $parts);
    }
}
