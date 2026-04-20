<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Rpc;

use function is_array;
use function is_int;
use function is_string;

/**
 * Reads the shape-checked fragments of an LSP request's `params` so handlers
 * don't duplicate the same `is_array`/`is_string`/`is_int` dance.
 *
 * Each method returns a safe default (empty string, `null`, `false`) when
 * the field is missing or has the wrong type. Handlers then translate that
 * default into the LSP response that best fits their method (empty array,
 * `null`, etc.).
 */
final class ParamsExtractor
{
    /**
     * @param array<string, mixed> $params
     */
    public function uri(array $params): string
    {
        $textDocument = $params['textDocument'] ?? [];
        if (!is_array($textDocument)) {
            return '';
        }

        return is_string($textDocument['uri'] ?? null) ? $textDocument['uri'] : '';
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{line: int, character: int}|null
     */
    public function position(array $params): ?array
    {
        $position = $params['position'] ?? null;
        if (!is_array($position)) {
            return null;
        }

        $line = $position['line'] ?? null;
        $character = $position['character'] ?? null;
        if (!is_int($line) || !is_int($character)) {
            return null;
        }

        return ['line' => $line, 'character' => $character];
    }

    /**
     * @param array<string, mixed> $params
     */
    public function version(array $params, int $default = 0): int
    {
        $textDocument = $params['textDocument'] ?? [];
        if (!is_array($textDocument)) {
            return $default;
        }

        return is_int($textDocument['version'] ?? null) ? $textDocument['version'] : $default;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function languageId(array $params, string $default = 'phel'): string
    {
        $textDocument = $params['textDocument'] ?? [];
        if (!is_array($textDocument)) {
            return $default;
        }

        return is_string($textDocument['languageId'] ?? null) ? $textDocument['languageId'] : $default;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function text(array $params, string $default = ''): string
    {
        $textDocument = $params['textDocument'] ?? [];
        if (!is_array($textDocument)) {
            return $default;
        }

        return is_string($textDocument['text'] ?? null) ? $textDocument['text'] : $default;
    }

    /**
     * @param array<string, mixed> $range
     */
    public function isValidRange(array $range): bool
    {
        $start = $range['start'] ?? null;
        $end = $range['end'] ?? null;

        return is_array($start)
            && is_array($end)
            && is_int($start['line'] ?? null)
            && is_int($start['character'] ?? null)
            && is_int($end['line'] ?? null)
            && is_int($end['character'] ?? null);
    }
}
