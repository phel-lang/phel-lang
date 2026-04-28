<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Document;

use function count;
use function explode;
use function max;
use function min;
use function preg_match;
use function str_replace;
use function strlen;

/**
 * In-memory view of a text document opened by the editor. Version is
 * incremented on every `didChange`.
 */
final class Document
{
    public function __construct(
        public readonly string $uri,
        public string $languageId,
        public int $version,
        public string $text,
    ) {}

    public function update(string $text, int $version): void
    {
        $this->text = $text;
        $this->version = $version;
    }

    /**
     * Record a new version without changing the text; useful after a batch
     * of `applyRange` mutations that already updated `text` incrementally.
     */
    public function bumpVersion(int $version): void
    {
        $this->version = $version;
    }

    /**
     * Apply an LSP-style incremental change (range + text). Accepts
     * line/character counts in UTF-16 code units per spec v3.17 — for our
     * purposes we treat them as UTF-8 byte offsets, which is sufficient for
     * the ASCII-dominant Phel source that real clients send.
     *
     * @param array{start: array{line: int, character: int}, end: array{line: int, character: int}} $range
     */
    public function applyRange(array $range, string $newText): void
    {
        $startOffset = $this->positionToOffset($range['start']);
        $endOffset = $this->positionToOffset($range['end']);

        $before = substr($this->text, 0, $startOffset);
        $after = substr($this->text, $endOffset);
        $this->text = $before . $newText . $after;
    }

    public function lineCount(): int
    {
        return count(explode("\n", str_replace("\r\n", "\n", $this->text)));
    }

    /**
     * Convert an (LSP) 0-based {line, character} to a 0-based byte offset.
     *
     * @param array{line: int, character: int} $position
     */
    public function positionToOffset(array $position): int
    {
        $normalized = str_replace("\r\n", "\n", $this->text);
        $lines = explode("\n", $normalized);
        $line = max(0, $position['line']);
        $character = max(0, $position['character']);

        $offset = 0;
        for ($i = 0; $i < $line; ++$i) {
            if (!isset($lines[$i])) {
                return strlen($normalized);
            }

            $offset += strlen($lines[$i]) + 1;
        }

        if (!isset($lines[$line])) {
            return strlen($normalized);
        }

        $lineLen = strlen($lines[$line]);
        return $offset + min($character, $lineLen);
    }

    /**
     * Return the word (identifier) at the given LSP-style position, or '' when
     * nothing is under the cursor. The matcher is permissive: it accepts
     * Lisp identifier characters.
     *
     * @param array{line: int, character: int} $position
     */
    public function wordAt(array $position): string
    {
        $normalized = str_replace("\r\n", "\n", $this->text);
        $lines = explode("\n", $normalized);
        $line = max(0, $position['line']);
        if (!isset($lines[$line])) {
            return '';
        }

        $lineText = $lines[$line];
        $col = max(0, $position['character']);
        $col = min($col, strlen($lineText));

        $left = $col;
        while ($left > 0 && $this->isIdentChar($lineText[$left - 1])) {
            --$left;
        }

        $right = $col;
        while ($right < strlen($lineText) && $this->isIdentChar($lineText[$right])) {
            ++$right;
        }

        if ($right <= $left) {
            return '';
        }

        return substr($lineText, $left, $right - $left);
    }

    /**
     * 1-based {line, column} for cursor under the given (0-based) LSP position.
     * Returned as a tuple ready for ApiFacade::completeAtPoint.
     *
     * @param array{line: int, character: int} $position
     *
     * @return array{int, int}
     */
    public function oneBasedLineCol(array $position): array
    {
        return [
            max(1, $position['line'] + 1),
            max(1, $position['character'] + 1),
        ];
    }

    private function isIdentChar(string $char): bool
    {
        return preg_match('/[A-Za-z0-9\-_?!*+<>=\/\.]/', $char) === 1;
    }
}
