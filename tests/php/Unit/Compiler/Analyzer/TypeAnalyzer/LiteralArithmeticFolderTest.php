<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\LiteralArithmeticFolder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LiteralArithmeticFolderTest extends TestCase
{
    private LiteralArithmeticFolder $folder;

    protected function setUp(): void
    {
        $this->folder = new LiteralArithmeticFolder();
    }

    public function test_int_addition_keeps_int_type(): void
    {
        self::assertSame(3, $this->folder->compute('+', [1, 2]));
    }

    public function test_addition_overflow_bails_to_null_for_bigint_promotion(): void
    {
        // 9e18 + 9e18 = 1.8e19, beyond PHP's int range → folding must bail.
        self::assertNull($this->folder->compute('+', [9_000_000_000_000_000_000, 9_000_000_000_000_000_000]));
    }

    public function test_multiplication_overflow_bails_to_null(): void
    {
        self::assertNull($this->folder->compute('*', [9_000_000_000_000_000_000, 9_000_000_000_000_000_000]));
    }

    public function test_multiplication_at_int_max_boundary_bails_without_warning(): void
    {
        // 2 * 2^62 = 2^63 = PHP_INT_MAX + 1. `(float) PHP_INT_MAX` rounds up
        // to 2^63, so a naive `> PHP_INT_MAX` check misses the overflow and
        // reaches the lossy `(int)` cast, emitting a PHP warning. The fold
        // must bail on the exact float bound instead — no warning.
        set_error_handler(static function (int $severity, string $message): bool {
            throw new RuntimeException('Unexpected PHP warning during fold: ' . $message);
        }, E_WARNING);

        try {
            self::assertNull($this->folder->compute('*', [2, 4_611_686_018_427_387_904]));
            self::assertNull($this->folder->compute('+', [PHP_INT_MAX, 1]));
        } finally {
            restore_error_handler();
        }
    }

    public function test_equality_is_type_strict(): void
    {
        self::assertTrue($this->folder->compute('=', [1, 1]));
        self::assertFalse($this->folder->compute('=', [1, 1.0]));
    }

    public function test_ordering_promotes_numerically(): void
    {
        self::assertTrue($this->folder->compute('<', [1, 1.5]));
        self::assertFalse($this->folder->compute('>=', [1, 2]));
    }

    public function test_mod_is_floor_remainder(): void
    {
        self::assertSame(2, $this->folder->compute('mod', [-7, 3]));
    }

    public function test_quot_truncates_toward_zero(): void
    {
        self::assertSame(-2, $this->folder->compute('quot', [-7, 3]));
    }

    public function test_rem_has_sign_of_dividend(): void
    {
        self::assertSame(-1, $this->folder->compute('rem', [-7, 3]));
    }

    public function test_division_by_zero_is_not_folded(): void
    {
        self::assertNull($this->folder->compute('mod', [5, 0]));
        self::assertNull($this->folder->compute('quot', [5, 0]));
        self::assertNull($this->folder->compute('rem', [5, 0]));
    }

    public function test_abs_of_int_min_bails_to_null(): void
    {
        self::assertNull($this->folder->compute('abs', [PHP_INT_MIN]));
    }

    public function test_min_with_nan_bails_to_null(): void
    {
        self::assertNull($this->folder->compute('min', [1, NAN]));
    }

    public function test_inc_and_dec(): void
    {
        self::assertSame(2, $this->folder->compute('inc', [1]));
        self::assertSame(0, $this->folder->compute('dec', [1]));
    }

    public function test_arity_errors_stay_unfolded(): void
    {
        self::assertNull($this->folder->compute('-', []));
        self::assertNull($this->folder->compute('=', []));
        self::assertNull($this->folder->compute('abs', [1, 2]));
    }

    public function test_unknown_fn_is_not_folded(): void
    {
        self::assertNull($this->folder->compute('not-a-fold', [1, 2]));
    }
}
