<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Convert;

use Phel\Shared\Api\Completion;

/**
 * Convert Api Completion into LSP CompletionItem.
 *
 * LSP CompletionItemKind values: 1 = Text, 2 = Method, 3 = Function,
 * 6 = Variable, 14 = Keyword, ...
 *
 * @phpstan-import-type Range from PositionConverter
 * @phpstan-import-type TextEdit from PositionConverter
 *
 * @phpstan-type CompletionItem array{label: string, kind: int, detail: string, documentation: string, textEdit?: TextEdit}
 *
 * @internal
 */
final class CompletionConverter
{
    public const int KIND_TEXT = 1;

    public const int KIND_METHOD = 2;

    public const int KIND_FUNCTION = 3;

    public const int KIND_VARIABLE = 6;

    public const int KIND_MODULE = 9;

    public const int KIND_KEYWORD = 14;

    /**
     * `$variableRange`, when given, is the span of the `$...` token under the
     * cursor. Variable completions carry an explicit `textEdit` over it because
     * a client's own word range stops at the `$` sigil, so accepting
     * `$_SERVER` at `php/$_S|` would otherwise yield `php/$_$_SERVER`.
     *
     * @param Range|null $variableRange
     *
     * @return CompletionItem
     */
    public function fromCompletion(Completion $completion, ?array $variableRange = null): array
    {
        $item = [
            'label' => $completion->label,
            'kind' => $this->kindForCompletion($completion->kind),
            'detail' => $completion->detail,
            'documentation' => $completion->documentation,
        ];

        if ($variableRange !== null && $completion->kind === Completion::KIND_VARIABLE) {
            $item['textEdit'] = [
                'range' => $variableRange,
                'newText' => $completion->label,
            ];
        }

        return $item;
    }

    private function kindForCompletion(string $kind): int
    {
        return match ($kind) {
            Completion::KIND_LOCAL, Completion::KIND_VARIABLE => self::KIND_VARIABLE,
            Completion::KIND_GLOBAL => self::KIND_FUNCTION,
            Completion::KIND_MACRO => self::KIND_METHOD,
            Completion::KIND_REQUIRE => self::KIND_MODULE,
            Completion::KIND_KEYWORD => self::KIND_KEYWORD,
            default => self::KIND_TEXT,
        };
    }
}
