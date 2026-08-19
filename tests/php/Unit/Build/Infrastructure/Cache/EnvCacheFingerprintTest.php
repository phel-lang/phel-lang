<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Infrastructure\Cache;

use Override;
use Phel\Build\Infrastructure\Cache\EnvCacheFingerprint;
use PHPUnit\Framework\TestCase;

use function getenv;
use function putenv;

final class EnvCacheFingerprintTest extends TestCase
{
    private const string VAR_A = 'PHEL_TEST_ENV_FINGERPRINT_A';

    private const string VAR_B = 'PHEL_TEST_ENV_FINGERPRINT_B';

    /** @var array<string, string|null> */
    private array $originalEnv = [];

    #[Override]
    protected function setUp(): void
    {
        foreach ([self::VAR_A, self::VAR_B] as $name) {
            $value = getenv($name);
            $this->originalEnv[$name] = $value === false ? null : $value;
            putenv($name);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $name => $value) {
            if ($value === null) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }
    }

    public function test_no_declared_vars_produces_no_fingerprint(): void
    {
        putenv(self::VAR_A . '=whatever');

        self::assertSame('', EnvCacheFingerprint::of([]));
    }

    public function test_an_unset_var_differs_from_an_empty_one(): void
    {
        $unset = EnvCacheFingerprint::of([self::VAR_A]);

        putenv(self::VAR_A . '=');
        $empty = EnvCacheFingerprint::of([self::VAR_A]);

        self::assertNotSame($unset, $empty);
    }

    public function test_a_value_change_flips_the_fingerprint(): void
    {
        putenv(self::VAR_A . '=a');
        $first = EnvCacheFingerprint::of([self::VAR_A]);

        putenv(self::VAR_A . '=b');

        self::assertNotSame($first, EnvCacheFingerprint::of([self::VAR_A]));
    }

    public function test_the_declared_order_does_not_matter(): void
    {
        putenv(self::VAR_A . '=a');
        putenv(self::VAR_B . '=b');

        self::assertSame(
            EnvCacheFingerprint::of([self::VAR_A, self::VAR_B]),
            EnvCacheFingerprint::of([self::VAR_B, self::VAR_A]),
        );
    }

    public function test_a_repeated_var_is_declared_once(): void
    {
        putenv(self::VAR_A . '=a');

        self::assertSame(
            EnvCacheFingerprint::of([self::VAR_A]),
            EnvCacheFingerprint::of([self::VAR_A, self::VAR_A]),
        );
    }

    public function test_a_value_cannot_forge_another_pair(): void
    {
        // Both states join to "A=1|B=2|B=" when name and raw value are
        // concatenated, so the pairs must not be spelled out verbatim.
        putenv(self::VAR_A . '=1|' . self::VAR_B . '=2');
        putenv(self::VAR_B . '=');
        $forged = EnvCacheFingerprint::of([self::VAR_A, self::VAR_B]);

        putenv(self::VAR_A . '=1');
        putenv(self::VAR_B . '=2|' . self::VAR_B . '=');

        self::assertNotSame($forged, EnvCacheFingerprint::of([self::VAR_A, self::VAR_B]));
    }
}
