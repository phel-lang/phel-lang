<?php

declare(strict_types=1);

namespace PhelTest\Unit\Internal\Domain;

use Phel;
use Phel\Api\Application\PhelFnGroupKeyGenerator;
use Phel\Api\Application\PhelFnNormalizer;
use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Api\Transfer\PhelFunction;
use Phel\Lang\Keyword;
use PHPUnit\Framework\TestCase;

final class PhelFnNormalizerTest extends TestCase
{
    public function test_no_functions_found(): void
    {
        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        self::assertSame([], $actual);
    }

    public function test_group_key_one_function(): void
    {
        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'fn-name' => Phel::map(),
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'fn-name',
                'doc' => '',
                'signatures' => [],
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
            'fn-name-1' => Phel::map(),
            'fn-name-2' => Phel::map(),
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'fn-name-1',
                'doc' => '',
                'signatures' => [],
                'desc' => '',
                'groupKey' => 'fn-name-1',
                'namespace' => 'core',
            ]),
            PhelFunction::fromArray([
                'name' => 'fn-name-2',
                'doc' => '',
                'signatures' => [],
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
            'fn-name' => Phel::map(),
            'fn-name?' => Phel::map(),
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'fn-name',
                'doc' => '',
                'signatures' => [],
                'desc' => '',
                'groupKey' => 'fn-name',
                'namespace' => 'core',
            ]),
            PhelFunction::fromArray([
                'name' => 'fn-name?',
                'doc' => '',
                'signatures' => [],
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
            'fn-name?' => Phel::map(),
            'fn-name-' => Phel::map(),
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'fn-name-',
                'doc' => '',
                'signatures' => [],
                'desc' => '',
                'groupKey' => 'fn-name',
                'namespace' => 'core',
            ]),
            PhelFunction::fromArray([
                'name' => 'fn-name?',
                'doc' => '',
                'signatures' => [],
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
            'fn-name-' => Phel::map(),
            'FN-NAME' => Phel::map(),
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'FN-NAME',
                'doc' => '',
                'signatures' => [],
                'desc' => '',
                'groupKey' => 'fn-name',
                'namespace' => 'core',
            ]),
            PhelFunction::fromArray([
                'name' => 'fn-name-',
                'doc' => '',
                'signatures' => [],
                'desc' => '',
                'groupKey' => 'fn-name',
                'namespace' => 'core',
            ]),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_skip_private_symbol(): void
    {
        $privateSymbol = Phel::map(
            Keyword::create('private'),
            true,
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'privateSymbol' => $privateSymbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());

        self::assertEmpty($normalizer->getPhelFunctions());
    }

    public function test_symbol_without_doc(): void
    {
        $symbol = Phel::map();

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            '*build-mode*' => $symbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => '*build-mode*',
                'doc' => '',
                'signatures' => [],
                'desc' => '',
                'groupKey' => 'build-mode',
                'namespace' => 'core',
            ]),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_symbol_with_doc_and_desc(): void
    {
        $symbol = Phel::map(
            Keyword::create('doc'),
            'Constant for Not a Number (NAN) values.',
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'NAN' => $symbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'NAN',
                'doc' => 'Constant for Not a Number (NAN) values.',
                'signatures' => [],
                'desc' => 'Constant for Not a Number (NAN) values.',
                'groupKey' => 'nan',
                'namespace' => 'core',
                'meta' => [
                    'doc' => 'Constant for Not a Number (NAN) values.',
                ],
            ]),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_symbol_with_doc_and_desc_and_signature(): void
    {
        $symbol = Phel::map(
            Keyword::create('doc'),
            "```phel\n(array & xs)\n```\nCreates a new Array.",
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'array' => $symbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'array',
                'doc' => "```phel\n(array & xs)\n```\nCreates a new Array.",
                'signatures' => ['(array & xs)'],
                'desc' => 'Creates a new Array.',
                'groupKey' => 'array',
                'namespace' => 'core',
                'meta' => [
                    'doc' => "```phel\n(array & xs)\n```\nCreates a new Array.",
                ],
            ]),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_symbol_with_desc_with_link(): void
    {
        $symbol = Phel::map(
            Keyword::create('doc'),
            "```phel\n(array & xs)\n```\nReturns a formatted string. See PHP's [sprintf](https://example.com) for more information.",
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'format' => $symbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'format',
                'doc' => "```phel\n(array & xs)\n```\nReturns a formatted string. See PHP's [sprintf](https://example.com) for more information.",
                'signatures' => ['(array & xs)'],
                'desc' => "Returns a formatted string. See PHP's [sprintf](https://example.com) for more information.",
                'groupKey' => 'format',
                'namespace' => 'core',
                'meta' => [
                    'doc' => "```phel\n(array & xs)\n```\nReturns a formatted string. See PHP's [sprintf](https://example.com) for more information.",
                ],
            ]),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_symbol_with_deprecated_meta(): void
    {
        $meta = Phel::map(
            Keyword::create('deprecated'),
            'Use new-fn',
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'old-fn' => $meta,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'old-fn',
                'doc' => '',
                'signatures' => [],
                'desc' => '',
                'groupKey' => 'old-fn',
                'namespace' => 'core',
                'meta' => [
                    'deprecated' => 'Use new-fn',
                ],
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

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'fn-name',
                'doc' => '',
                'signatures' => [],
                'desc' => '',
                'groupKey' => 'fn-name',
                'githubUrl' => 'https://github.com/phel-lang/phel-lang/blob/main/src/phel/my-file.phel#L5',
                'file' => 'src/phel/my-file.phel',
                'line' => 5,
                'namespace' => 'core',
                'meta' => [
                    'start-location' => Phel::map(
                        Keyword::create('file'),
                        '/var/www/project/src/phel/my-file.phel',
                        Keyword::create('line'),
                        5,
                    ),
                ],
            ]),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_normalize_native_symbol_doc_url(): void
    {
        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'apply' => Phel::map(),
        ]);
        $phelFnLoader->method('getNormalizedNativeSymbols')->willReturn([
            'apply' => ['docUrl' => 'https://docs'],
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'apply',
                'doc' => '',
                'signatures' => [],
                'desc' => '',
                'groupKey' => 'apply',
                'githubUrl' => '',
                'docUrl' => 'https://docs',
                'namespace' => 'core',
                'meta' => ['example' => ''],
            ]),
        ];

        self::assertEquals($expected, $actual);
    }

    public function test_multi_arity_function_signature(): void
    {
        $symbol = Phel::map(
            Keyword::create('doc'),
            "```phel\n(conj coll x)\n(conj coll x & xs)\n```\nReturns a new collection with elements added.",
        );

        $phelFnLoader = $this->createMock(PhelFnLoaderInterface::class);
        $phelFnLoader->method('getNormalizedPhelFunctions')->willReturn([
            'conj' => $symbol,
        ]);

        $normalizer = new PhelFnNormalizer($phelFnLoader, new PhelFnGroupKeyGenerator());
        $actual = $normalizer->getPhelFunctions();

        $expected = [
            PhelFunction::fromArray([
                'name' => 'conj',
                'doc' => "```phel\n(conj coll x)\n(conj coll x & xs)\n```\nReturns a new collection with elements added.",
                'signatures' => ['(conj coll x)', '(conj coll x & xs)'],
                'desc' => 'Returns a new collection with elements added.',
                'groupKey' => 'conj',
                'namespace' => 'core',
                'meta' => [
                    'doc' => "```phel\n(conj coll x)\n(conj coll x & xs)\n```\nReturns a new collection with elements added.",
                ],
            ]),
        ];

        self::assertEquals($expected, $actual);
    }
}
