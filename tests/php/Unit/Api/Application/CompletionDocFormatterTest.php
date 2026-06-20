<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\CompletionDocFormatter;
use Phel\Api\Transfer\PhelFunction;
use PHPUnit\Framework\TestCase;

use function str_repeat;
use function strlen;

final class CompletionDocFormatterTest extends TestCase
{
    private CompletionDocFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new CompletionDocFormatter();
    }

    public function test_combines_signature_and_summary(): void
    {
        $fn = PhelFunction::fromArray([
            'name' => 'map',
            'signatures' => ['(map f coll)'],
            'desc' => 'Returns a lazy sequence.',
        ]);

        self::assertSame('(map f coll): Returns a lazy sequence.', $this->formatter->format($fn));
    }

    public function test_falls_back_to_name_when_no_signature(): void
    {
        $fn = PhelFunction::fromArray(['name' => 'x', 'desc' => 'A var.']);

        self::assertSame('x: A var.', $this->formatter->format($fn));
    }

    public function test_signature_only_when_no_summary(): void
    {
        $fn = PhelFunction::fromArray(['name' => 'foo', 'signatures' => ['(foo a)']]);

        self::assertSame('(foo a)', $this->formatter->format($fn));
    }

    public function test_uses_doc_when_description_empty(): void
    {
        $fn = PhelFunction::fromArray([
            'name' => 'foo',
            'signatures' => ['(foo)'],
            'doc' => "line one\nline two",
        ]);

        self::assertSame('(foo): line one line two', $this->formatter->format($fn));
    }

    public function test_truncates_long_summaries(): void
    {
        $fn = PhelFunction::fromArray([
            'name' => 'foo',
            'signatures' => ['(foo)'],
            'desc' => str_repeat('word ', 60),
        ]);

        $result = (string) $this->formatter->format($fn);
        self::assertStringEndsWith('...', $result);
        self::assertLessThan(130, strlen($result));
    }

    public function test_null_when_nothing_to_show(): void
    {
        self::assertNull($this->formatter->format(PhelFunction::fromArray([])));
    }
}
