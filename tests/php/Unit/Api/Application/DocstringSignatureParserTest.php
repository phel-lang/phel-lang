<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\DocstringSignatureParser;
use PHPUnit\Framework\TestCase;

final class DocstringSignatureParserTest extends TestCase
{
    public function test_returns_empty_signatures_and_description_for_empty_input(): void
    {
        self::assertSame(
            ['signatures' => [], 'description' => ''],
            DocstringSignatureParser::parse(''),
        );
    }

    public function test_extracts_single_signature_and_description(): void
    {
        $doc = "```phel\n(my-fn arg)\n```\nDoes a thing.";

        $parsed = DocstringSignatureParser::parse($doc);

        self::assertSame(['(my-fn arg)'], $parsed['signatures']);
        self::assertSame('Does a thing.', $parsed['description']);
    }

    public function test_extracts_multiple_signatures(): void
    {
        $doc = "```phel\n(f a)\n(f a b)\n(f a b & rest)\n```\nMulti-arity.";

        $parsed = DocstringSignatureParser::parse($doc);

        self::assertSame(
            ['(f a)', '(f a b)', '(f a b & rest)'],
            $parsed['signatures'],
        );
        self::assertSame('Multi-arity.', $parsed['description']);
    }

    public function test_handles_docstring_with_no_signature_block(): void
    {
        $parsed = DocstringSignatureParser::parse('Just a description.');

        self::assertSame([], $parsed['signatures']);
        self::assertSame('Just a description.', $parsed['description']);
    }

    public function test_skips_blank_lines_between_signatures(): void
    {
        $doc = "```phel\n(f a)\n\n(f a b)\n```\n";

        $parsed = DocstringSignatureParser::parse($doc);

        self::assertSame(['(f a)', '(f a b)'], $parsed['signatures']);
    }
}
