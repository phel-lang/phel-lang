<?php

declare(strict_types=1);

namespace PhelTest\Unit\Internal\Domain;

use Phel\Api\Domain\PhelFnNormalizer;
use Phel\Api\Infrastructure\PhelFnLoaderInterface;
use Phel\Api\Transfer\NormalizedPhelFunction;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use PHPUnit\Framework\TestCase;

final class PhelFnNormalizerTest extends TestCase
{
    public function test_group_key_one_function(): void
    {
        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'fn-name' => $this->createMock(PersistentMapInterface::class),
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getNormalizedGroupedFunctions();

        $expected = [
            'fn-name' => [
                NormalizedPhelFunction::fromArray([
                    'fnName' => 'fn-name',
                    'doc' => '',
                    'fnSignature' => '',
                    'desc' => '',
                ]),
            ],
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_group_key_functions_in_different_groups(): void
    {
        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'fn-name-1' => $this->createMock(PersistentMapInterface::class),
            'fn-name-2' => $this->createMock(PersistentMapInterface::class),
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getNormalizedGroupedFunctions();

        $expected = [
            'fn-name-1' => [
                NormalizedPhelFunction::fromArray([
                    'fnName' => 'fn-name-1',
                    'doc' => '',
                    'fnSignature' => '',
                    'desc' => '',
                ]),
            ],
            'fn-name-2' => [
                NormalizedPhelFunction::fromArray([
                    'fnName' => 'fn-name-2',
                    'doc' => '',
                    'fnSignature' => '',
                    'desc' => '',
                ]),
            ],
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_group_key_functions_in_same_group_with_question_mark(): void
    {
        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'fn-name' => $this->createMock(PersistentMapInterface::class),
            'fn-name?' => $this->createMock(PersistentMapInterface::class),
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getNormalizedGroupedFunctions();

        $expected = [
            'fn-name' => [
                NormalizedPhelFunction::fromArray([
                    'fnName' => 'fn-name',
                    'doc' => '',
                    'fnSignature' => '',
                    'desc' => '',
                ]),
                NormalizedPhelFunction::fromArray([
                    'fnName' => 'fn-name?',
                    'doc' => '',
                    'fnSignature' => '',
                    'desc' => '',
                ]),
            ],
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_group_key_functions_in_same_group_with_minus(): void
    {
        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'fn-name?' => $this->createMock(PersistentMapInterface::class),
            'fn-name-' => $this->createMock(PersistentMapInterface::class),
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getNormalizedGroupedFunctions();

        $expected = [
            'fn-name' => [
                NormalizedPhelFunction::fromArray([
                    'fnName' => 'fn-name?',
                    'doc' => '',
                    'fnSignature' => '',
                    'desc' => '',
                ]),
                NormalizedPhelFunction::fromArray([
                    'fnName' => 'fn-name-',
                    'doc' => '',
                    'fnSignature' => '',
                    'desc' => '',
                ]),
            ],
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_group_key_functions_in_same_group_with_upper_case(): void
    {
        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'fn-name-' => $this->createMock(PersistentMapInterface::class),
            'FN-NAME' => $this->createMock(PersistentMapInterface::class),
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getNormalizedGroupedFunctions();

        $expected = [
            'fn-name' => [
                NormalizedPhelFunction::fromArray([
                    'fnName' => 'fn-name-',
                    'doc' => '',
                    'fnSignature' => '',
                    'desc' => '',
                ]),
                NormalizedPhelFunction::fromArray([
                    'fnName' => 'FN-NAME',
                    'doc' => '',
                    'fnSignature' => '',
                    'desc' => '',
                ]),
            ],
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_skip_private_symbol(): void
    {
        $privateSymbol = $this->createMock(PersistentMapInterface::class);
        // Mocking the `$meta[Keyword::create('private')]`
        $privateSymbol->method('offsetExists')->willReturn(true);
        $privateSymbol->method('offsetGet')->willReturn(true);

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'privateSymbol' => $privateSymbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);

        self::assertEmpty($normalizer->getNormalizedGroupedFunctions());
    }

    public function test_symbol_without_doc(): void
    {
        $symbol = $this->createStub(PersistentMapInterface::class);
        $symbol->method('offsetExists')->willReturn(true);
        $symbol->method('offsetGet')->willReturnOnConsecutiveCalls(
            false, // relates to 'isPrivate'
            null, // relates to 'doc'
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            '*compile-mode*' => $symbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getNormalizedGroupedFunctions();

        $expected = [
            'compile-mode' => [
                NormalizedPhelFunction::fromArray([
                    'fnName' => '*compile-mode*',
                    'doc' => '',
                    'fnSignature' => '',
                    'desc' => '',
                ]),
            ],
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_symbol_with_doc_and_desc(): void
    {
        $symbol = $this->createStub(PersistentMapInterface::class);
        $symbol->method('offsetExists')->willReturn(true);
        $symbol->method('offsetGet')->willReturnOnConsecutiveCalls(
            false, // relates to 'isPrivate'
            'Constant for Not a Number (NAN) values.', // relates to 'doc'
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'NAN' => $symbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getNormalizedGroupedFunctions();

        $expected = [
            'nan' => [
                NormalizedPhelFunction::fromArray([
                    'fnName' => 'NAN',
                    'doc' => 'Constant for Not a Number (NAN) values.',
                    'fnSignature' => '',
                    'desc' => 'Constant for Not a Number (NAN) values.',
                ]),
            ],
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_symbol_with_doc_and_desc_and_signature(): void
    {
        $symbol = $this->createStub(PersistentMapInterface::class);
        $symbol->method('offsetExists')->willReturn(true);
        $symbol->method('offsetGet')->willReturnOnConsecutiveCalls(
            false, // relates to 'isPrivate'
            "```phel\n(array & xs)\n```\nCreates a new Array.", // relates to 'doc'
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'array' => $symbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getNormalizedGroupedFunctions();

        $expected = [
            'array' => [
                NormalizedPhelFunction::fromArray([
                    'fnName' => 'array',
                    'doc' => "```phel\n(array & xs)\n```\nCreates a new Array.",
                    'fnSignature' => '(array & xs)',
                    'desc' => 'Creates a new Array.',
                ]),
            ],
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_symbol_with_desc_with_link(): void
    {
        $symbol = $this->createStub(PersistentMapInterface::class);
        $symbol->method('offsetExists')->willReturn(true);
        $symbol->method('offsetGet')->willReturnOnConsecutiveCalls(
            false, // relates to 'isPrivate'
            "```phel\n(array & xs)\n```\nReturns a formatted string. See PHP's [sprintf](https://example.com) for more information.", // relates to 'doc'
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'format' => $symbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getNormalizedGroupedFunctions();

        $expected = [
            'format' => [
                NormalizedPhelFunction::fromArray([
                    'fnName' => 'format',
                    'doc' => "```phel\n(array & xs)\n```\nReturns a formatted string. See PHP's [sprintf](https://example.com) for more information.",
                    'fnSignature' => '(array & xs)',
                    'desc' => "Returns a formatted string. See PHP's [sprintf](https://example.com) for more information.",
                ]),
            ],
        ];

        self::assertEquals($expected, $actual);
    }
}
