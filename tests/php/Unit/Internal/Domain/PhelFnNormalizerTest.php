<?php

declare(strict_types=1);

namespace PhelTest\Unit\Internal\Domain;

use Phel;
use Phel\Api\Application\PhelFnNormalizer;
use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Api\Transfer\PhelFunction;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use PHPUnit\Framework\TestCase;

final class PhelFnNormalizerTest extends TestCase
{
    public function test_no_functions_found(): void
    {
        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getPhelFunctions();

        self::assertSame([], $actual);
    }

    public function test_group_key_one_function(): void
    {
        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'fn-name' => $this->createMock(PersistentMapInterface::class),
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'fn-name',
                'doc' => '',
                'fnSignature' => '',
                'desc' => '',
                'groupKey' => 'fn-name',
                'namespace' => 'core',
            ]),
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
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'fn-name-1',
                'doc' => '',
                'fnSignature' => '',
                'desc' => '',
                'groupKey' => 'fn-name-1',
                'namespace' => 'core',
            ]),
            PhelFunction::fromArray([
                'name' => 'fn-name-2',
                'doc' => '',
                'fnSignature' => '',
                'desc' => '',
                'groupKey' => 'fn-name-2',
                'namespace' => 'core',
            ]),
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
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'fn-name',
                'doc' => '',
                'fnSignature' => '',
                'desc' => '',
                'groupKey' => 'fn-name',
                'namespace' => 'core',
            ]),
            PhelFunction::fromArray([
                'name' => 'fn-name?',
                'doc' => '',
                'fnSignature' => '',
                'desc' => '',
                'groupKey' => 'fn-name',
                'namespace' => 'core',
            ]),
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
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'fn-name-',
                'doc' => '',
                'fnSignature' => '',
                'desc' => '',
                'groupKey' => 'fn-name',
                'namespace' => 'core',
            ]),
            PhelFunction::fromArray([
                'name' => 'fn-name?',
                'doc' => '',
                'fnSignature' => '',
                'desc' => '',
                'groupKey' => 'fn-name',
                'namespace' => 'core',
            ]),
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
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'FN-NAME',
                'doc' => '',
                'fnSignature' => '',
                'desc' => '',
                'groupKey' => 'fn-name',
                'namespace' => 'core',
            ]),
            PhelFunction::fromArray([
                'name' => 'fn-name-',
                'doc' => '',
                'fnSignature' => '',
                'desc' => '',
                'groupKey' => 'fn-name',
                'namespace' => 'core',
            ]),
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

        self::assertEmpty($normalizer->getPhelFunctions());
    }

    public function test_symbol_without_doc(): void
    {
        $symbol = $this->createStub(PersistentMapInterface::class);
        $symbol->method('offsetExists')->willReturn(true);
        $symbol->method('offsetGet')->willReturnOnConsecutiveCalls(
            false, // relates to 'isPrivate'
            null, // relates to 'doc'
            null, // relates to 'start-location'
            null, // relates to 'docUrl'
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            '*build-mode*' => $symbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => '*build-mode*',
                'doc' => '',
                'fnSignature' => '',
                'desc' => '',
                'groupKey' => 'build-mode',
                'namespace' => 'core',
            ]),
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
            null, // relates to 'start-location'
            null, // relates to 'docUrl'
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'NAN' => $symbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'NAN',
                'doc' => 'Constant for Not a Number (NAN) values.',
                'rawDoc' => 'Constant for Not a Number (NAN) values.',
                'fnSignature' => '',
                'desc' => 'Constant for Not a Number (NAN) values.',
                'groupKey' => 'nan',
                'namespace' => 'core',
            ]),
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
            null, // relates to 'start-location'
            null, // relates to 'docUrl'
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'array' => $symbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'array',
                'doc' => "```phel\n(array & xs)\n```\nCreates a new Array.",
                'rawDoc' => "(array & xs)\nCreates a new Array.",
                'fnSignature' => '(array & xs)',
                'desc' => 'Creates a new Array.',
                'groupKey' => 'array',
                'namespace' => 'core',
            ]),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_symbol_with_desc_with_link(): void
    {
        $symbol = $this->createStub(PersistentMapInterface::class);
        $symbol->method('offsetExists')->willReturn(true);
        $symbol->method('offsetGet')->willReturnOnConsecutiveCalls(
            false, // relates to 'isPrivate'
            "```phel\n(array & xs)\n```\nReturns a formatted string. See PHP's [sprintf](https://example.com) for more information.",
            // relates to 'doc'
            null, // relates to 'start-location'
            null, // relates to 'docUrl'
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'format' => $symbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'format',
                'doc' => "```phel\n(array & xs)\n```\nReturns a formatted string. See PHP's [sprintf](https://example.com) for more information.",
                'rawDoc' => "(array & xs)\nReturns a formatted string. See PHP's [sprintf](https://example.com) for more information.",
                'fnSignature' => '(array & xs)',
                'desc' => "Returns a formatted string. See PHP's [sprintf](https://example.com) for more information.",
                'groupKey' => 'format',
                'namespace' => 'core',
            ]),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_returns_source_location(): void
    {
        $meta = Phel::map(
            Keyword::create('start-location'),
            Phel::map(
                Keyword::create('file'),
                '/var/www/project/src/phel/my-file.phel',
                Keyword::create('line'),
                5,
            ),
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'fn-name' => $meta,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'fn-name',
                'doc' => '',
                'fnSignature' => '',
                'desc' => '',
                'groupKey' => 'fn-name',
                'githubUrl' => 'https://github.com/phel-lang/phel-lang/blob/main/src/phel/my-file.phel#L5',
                'file' => 'src/phel/my-file.phel',
                'line' => 5,
                'namespace' => 'core',
            ]),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_normalize_native_symbol_doc_url(): void
    {
        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'apply' => $this->createMock(PersistentMapInterface::class),
        ]);
        $phelFnLoader->method('getNormalizedNativeSymbols')->willReturn([
            'apply' => ['docUrl' => 'https://docs'],
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader);
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'apply',
                'doc' => '',
                'fnSignature' => '',
                'desc' => '',
                'groupKey' => 'apply',
                'githubUrl' => '',
                'docUrl' => 'https://docs',
                'namespace' => 'core',
            ]),
        ];

        self::assertEquals($expected, $actual);
    }
}
