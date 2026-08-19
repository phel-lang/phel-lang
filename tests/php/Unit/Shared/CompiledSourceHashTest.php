<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\CompiledSourceHash;
use PHPUnit\Framework\TestCase;

use function md5;

final class CompiledSourceHashTest extends TestCase
{
    private const string CODE = '(ns app\\main)';

    public function test_level_zero_without_env_keeps_the_historical_plain_hash(): void
    {
        self::assertSame(md5(self::CODE), CompiledSourceHash::of(self::CODE, 0));
    }

    public function test_the_optimization_level_is_mixed_in(): void
    {
        self::assertSame(md5(self::CODE . '|O2'), CompiledSourceHash::of(self::CODE, 2));
    }

    public function test_an_env_fingerprint_moves_the_level_zero_hash(): void
    {
        $hash = CompiledSourceHash::of(self::CODE, 0, 'abc');

        self::assertSame(md5(self::CODE . '|Eabc'), $hash);
        self::assertNotSame(md5(self::CODE), $hash);
    }

    public function test_both_axes_are_mixed_in_together(): void
    {
        self::assertSame(
            md5(self::CODE . '|O2|Eabc'),
            CompiledSourceHash::of(self::CODE, 2, 'abc'),
        );
    }

    public function test_a_different_env_fingerprint_is_a_different_hash(): void
    {
        self::assertNotSame(
            CompiledSourceHash::of(self::CODE, 0, 'abc'),
            CompiledSourceHash::of(self::CODE, 0, 'def'),
        );
        self::assertNotSame(
            CompiledSourceHash::of(self::CODE, 2, 'abc'),
            CompiledSourceHash::of(self::CODE, 2, 'def'),
        );
    }
}
