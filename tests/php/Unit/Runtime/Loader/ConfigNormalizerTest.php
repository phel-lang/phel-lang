<?php

declare(strict_types=1);

namespace PhelTest\Unit\Runtime\Loader;

use Phel\Runtime\Loader\ConfigNormalizer;
use PHPUnit\Framework\TestCase;

final class ConfigNormalizerTest extends TestCase
{
    private ConfigNormalizer $configLoader;

    public function setUp(): void
    {
        $this->configLoader = new ConfigNormalizer();
    }

    public function test_normalize_empty_config(): void
    {
        $phelConfig = [];

        $result = $this->configLoader->normalize($phelConfig);

        self::assertSame([], $result);
    }

    public function test_normalize_empty_key(): void
    {
        $phelConfig = [
            'key-1' => [],
        ];

        $result = $this->configLoader->normalize($phelConfig);

        self::assertSame(['key-1' => []], $result);
    }

    public function test_normalize_one_key_with_string_value(): void
    {
        $phelConfig = [
            'key-1' => 'value-1',
        ];

        $result = $this->configLoader->normalize($phelConfig, 'prefix');

        self::assertSame([
            'key-1' => [
                'prefix/value-1',
            ],
        ], $result);
    }

    public function test_normalize_one_key_with_array_value(): void
    {
        $phelConfig = [
            'key-1' => [
                'sub-key-1' => 'sub-value-1',
            ],
        ];

        $result = $this->configLoader->normalize($phelConfig, 'prefix');

        self::assertSame([
            'key-1' => [
                'prefix/sub-value-1',
            ],
        ], $result);
    }

    public function test_normalize_two_keys_with_mixed_values(): void
    {
        $phelConfig = [
            'key-1' => 'sub-value-1',
            'key-2' => [
                'sub-key-2' => 'sub-value-2',
            ],
        ];

        $result = $this->configLoader->normalize($phelConfig, 'prefix');

        self::assertSame([
            'key-1' => [
                'prefix/sub-value-1',
            ],
            'key-2' => [
                'prefix/sub-value-2',
            ],
        ], $result);
    }
}
