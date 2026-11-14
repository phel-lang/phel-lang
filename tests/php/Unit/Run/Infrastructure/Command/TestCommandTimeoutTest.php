<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Infrastructure\Command;

use InvalidArgumentException;
use Phel\Run\Infrastructure\Command\TestCommand;
use Phel\Run\Infrastructure\Command\TestCommandOptionParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;

final class TestCommandTimeoutTest extends TestCase
{
    public function test_no_timeout_means_no_limit(): void
    {
        self::assertNull($this->decideTimeout([], workerCount: 4));
        self::assertNull($this->decideTimeout([], workerCount: null));
    }

    public function test_a_timeout_with_parallel_workers_is_kept(): void
    {
        self::assertSame(30, $this->decideTimeout(['--timeout' => '30'], workerCount: 4));
    }

    public function test_a_timeout_on_a_serial_run_fails_fast(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--timeout needs a parallel run');

        $this->decideTimeout(['--timeout' => '30'], workerCount: null);
    }

    public function test_a_timeout_with_coverage_fails_fast_since_coverage_runs_serially(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--timeout needs a parallel run');

        $this->decideTimeout(['--timeout' => '30'], workerCount: 4, coverage: true);
    }

    public function test_a_timeout_below_one_second_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--timeout must be a whole number of at least 1, got "0"');

        $this->decideTimeout(['--timeout' => '0'], workerCount: 4);
    }

    public function test_a_non_numeric_timeout_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--timeout must be a whole number of at least 1, got "soon"');

        $this->decideTimeout(['--timeout' => 'soon'], workerCount: 4);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function decideTimeout(array $arguments, ?int $workerCount, bool $coverage = false): ?int
    {
        $input = new ArrayInput($arguments, new TestCommand()->getDefinition());

        return new TestCommandOptionParser()->decideTimeout($input, $workerCount, $coverage);
    }
}
