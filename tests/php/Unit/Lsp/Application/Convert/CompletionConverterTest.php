<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Convert;

use Generator;
use Phel\Lsp\Application\Convert\CompletionConverter;
use Phel\Shared\Api\Completion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompletionConverterTest extends TestCase
{
    private CompletionConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new CompletionConverter();
    }

    public function test_carries_label_detail_and_documentation(): void
    {
        $item = $this->converter->fromCompletion(
            new Completion('str_replace', Completion::KIND_GLOBAL, 'str_replace(...)', 'Replaces text.'),
        );

        self::assertSame('str_replace', $item['label']);
        self::assertSame('str_replace(...)', $item['detail']);
        self::assertSame('Replaces text.', $item['documentation']);
    }

    public static function provideKinds(): Generator
    {
        yield [Completion::KIND_LOCAL, CompletionConverter::KIND_VARIABLE];
        yield [Completion::KIND_VARIABLE, CompletionConverter::KIND_VARIABLE];
        yield [Completion::KIND_GLOBAL, CompletionConverter::KIND_FUNCTION];
        yield [Completion::KIND_MACRO, CompletionConverter::KIND_METHOD];
        yield [Completion::KIND_REQUIRE, CompletionConverter::KIND_MODULE];
        yield [Completion::KIND_KEYWORD, CompletionConverter::KIND_KEYWORD];
        yield ['something-else', CompletionConverter::KIND_TEXT];
    }

    #[DataProvider('provideKinds')]
    public function test_maps_completion_kind_to_lsp_kind(string $kind, int $expected): void
    {
        $item = $this->converter->fromCompletion(new Completion('x', $kind));

        self::assertSame($expected, $item['kind']);
    }

    public function test_no_text_edit_without_a_variable_range(): void
    {
        $item = $this->converter->fromCompletion(new Completion('$_SERVER', Completion::KIND_VARIABLE));

        self::assertArrayNotHasKey('textEdit', $item);
    }

    public function test_variable_completion_gets_a_text_edit_over_the_range(): void
    {
        $item = $this->converter->fromCompletion(
            new Completion('$_SERVER', Completion::KIND_VARIABLE),
            $this->range(5, 8),
        );

        self::assertSame([
            'range' => $this->range(5, 8),
            'newText' => '$_SERVER',
        ], $item['textEdit'] ?? null);
    }

    public function test_non_variable_completion_is_left_alone_inside_a_variable_range(): void
    {
        // One range is computed per request and handed to every item, so the
        // kind gate is what keeps it off the Phel symbols in the same list.
        $item = $this->converter->fromCompletion(
            new Completion('str_replace', Completion::KIND_GLOBAL),
            $this->range(5, 8),
        );

        self::assertArrayNotHasKey('textEdit', $item);
    }

    /**
     * @return array{start: array{line: int, character: int}, end: array{line: int, character: int}}
     */
    private function range(int $start, int $end): array
    {
        return [
            'start' => ['line' => 0, 'character' => $start],
            'end' => ['line' => 0, 'character' => $end],
        ];
    }
}
