<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\OrderedResultBuffer;
use Phel\Run\Application\Test\WorkerResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class OrderedResultBufferTest extends TestCase
{
    public function test_flushes_results_in_input_order_even_when_completion_is_out_of_order(): void
    {
        $output = new BufferedOutput();
        $buffer = new OrderedResultBuffer(3, $output);

        // Workers complete out of order: index 2, then 0, then 1.
        $buffer->record(new WorkerResult(2, 'phel.c', true, 'c-output', []));
        self::assertSame('', $output->fetch(), 'slot 0 still empty → nothing flushes yet');

        $buffer->record(new WorkerResult(0, 'phel.a', true, 'a-output', []));
        $afterIndex0 = $output->fetch();
        self::assertStringContainsString('phel.a', $afterIndex0);
        self::assertStringNotContainsString('phel.c', $afterIndex0, 'slot 1 still pending → cannot flush 2');

        $buffer->record(new WorkerResult(1, 'phel.b', true, 'b-output', []));
        $tail = $output->fetch();

        // After the missing slot 1 arrives, both 1 and 2 flush in order.
        self::assertStringContainsString('phel.b', $tail);
        self::assertStringContainsString('phel.c', $tail);
        self::assertLessThan(strpos($tail, 'phel.c'), strpos($tail, 'phel.b'));
    }

    public function test_overall_ok_flips_to_false_when_any_result_fails(): void
    {
        $buffer = new OrderedResultBuffer(2, new BufferedOutput());

        $buffer->record(new WorkerResult(0, 'phel.a', true, '', []));
        self::assertTrue($buffer->overallOk());

        $buffer->record(new WorkerResult(1, 'phel.b', false, '', ['phel.b/broken']));
        self::assertFalse($buffer->overallOk());
    }

    public function test_aggregates_failed_tests_across_results(): void
    {
        $buffer = new OrderedResultBuffer(2, new BufferedOutput());

        $buffer->record(new WorkerResult(0, 'phel.a', false, '', ['phel.a/one', 'phel.a/two']));
        $buffer->record(new WorkerResult(1, 'phel.b', false, '', ['phel.b/three']));

        self::assertSame(
            ['phel.a/one', 'phel.a/two', 'phel.b/three'],
            $buffer->allFailedTests(),
        );
    }

    public function test_is_complete_only_when_all_slots_recorded(): void
    {
        $buffer = new OrderedResultBuffer(2, new BufferedOutput());

        self::assertFalse($buffer->isComplete());

        $buffer->record(new WorkerResult(0, 'phel.a', true, '', []));
        self::assertFalse($buffer->isComplete());

        $buffer->record(new WorkerResult(1, 'phel.b', true, '', []));
        self::assertTrue($buffer->isComplete());
    }

    public function test_record_crash_synthesises_a_failed_result(): void
    {
        $output = new BufferedOutput();
        $buffer = new OrderedResultBuffer(1, $output);

        $buffer->recordCrash(0, 'phel.bad', "boom\n");

        self::assertTrue($buffer->isComplete());
        self::assertFalse($buffer->overallOk());
        self::assertStringContainsString('Worker died while running phel.bad', $output->fetch());
    }

    public function test_record_crash_with_null_index_is_a_no_op(): void
    {
        $buffer = new OrderedResultBuffer(1, new BufferedOutput());

        $buffer->recordCrash(null, 'phel.unknown', '');

        self::assertFalse($buffer->isComplete());
        self::assertTrue($buffer->overallOk());
    }

    public function test_flushed_block_includes_header_with_one_based_index(): void
    {
        $output = new BufferedOutput();
        $buffer = new OrderedResultBuffer(2, $output);

        $buffer->record(new WorkerResult(0, 'phel.alpha', true, 'alpha-output', []));

        $rendered = $output->fetch();

        self::assertStringContainsString('--- [1/2] phel.alpha ---', $rendered);
        self::assertStringContainsString('alpha-output', $rendered);
    }
}
