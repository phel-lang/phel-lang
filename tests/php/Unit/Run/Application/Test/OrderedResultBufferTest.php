<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\Counts;
use Phel\Run\Application\Test\OrderedResultBuffer;
use Phel\Run\Application\Test\WorkerResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class OrderedResultBufferTest extends TestCase
{
    public function test_passing_namespaces_do_not_print_their_captured_block(): void
    {
        $output = new BufferedOutput();
        $buffer = new OrderedResultBuffer(1, $output);

        $buffer->record(
            new WorkerResult(0, 'phel.alpha', true, 'noisy passing output', [], new Counts(pass: 5, total: 5)),
        );
        $buffer->finishProgress();

        $rendered = $output->fetch();
        self::assertStringNotContainsString('noisy passing output', $rendered);
        self::assertStringNotContainsString('--- phel.alpha ---', $rendered);
    }

    public function test_failing_namespaces_print_their_captured_block_under_a_header(): void
    {
        $output = new BufferedOutput();
        $buffer = new OrderedResultBuffer(1, $output);

        $buffer->record(new WorkerResult(
            0,
            'phel.broken',
            false,
            "FAIL: bad assertion\n",
            ['phel.broken/bad'],
            new Counts(pass: 0, failed: 1, total: 1),
        ));
        $buffer->finishProgress();

        $rendered = $output->fetch();
        self::assertStringContainsString('--- phel.broken ---', $rendered);
        self::assertStringContainsString('FAIL: bad assertion', $rendered);
    }

    public function test_flushes_results_in_input_order_even_when_completion_is_out_of_order(): void
    {
        $output = new BufferedOutput();
        $buffer = new OrderedResultBuffer(3, $output);

        // Workers complete out of order: index 2 first, then 0, then 1.
        // All three carry failures so the buffer prints each block.
        $buffer->record(new WorkerResult(2, 'phel.c', false, 'c-fail', [], new Counts(failed: 1, total: 1)));

        $afterIndex2 = $output->fetch();
        self::assertStringNotContainsString('phel.c', $afterIndex2, 'slot 0 still pending → nothing flushes');

        $buffer->record(new WorkerResult(0, 'phel.a', false, 'a-fail', [], new Counts(failed: 1, total: 1)));
        $afterIndex0 = $output->fetch();
        self::assertStringContainsString('phel.a', $afterIndex0);
        self::assertStringNotContainsString('phel.c', $afterIndex0, 'slot 1 still pending → cannot flush 2');

        $buffer->record(new WorkerResult(1, 'phel.b', false, 'b-fail', [], new Counts(failed: 1, total: 1)));
        $tail = $output->fetch();

        // After the missing slot 1 arrives, both 1 and 2 flush in order.
        self::assertStringContainsString('phel.b', $tail);
        self::assertStringContainsString('phel.c', $tail);
        self::assertLessThan(strpos($tail, 'phel.c'), strpos($tail, 'phel.b'));
    }

    public function test_overall_ok_flips_to_false_when_any_result_fails(): void
    {
        $buffer = new OrderedResultBuffer(2, new BufferedOutput());

        $buffer->record(new WorkerResult(0, 'phel.a', true, '', [], new Counts(pass: 1, total: 1)));
        self::assertTrue($buffer->overallOk());

        $buffer->record(new WorkerResult(1, 'phel.b', false, '', ['phel.b/broken'], new Counts(failed: 1, total: 1)));
        self::assertFalse($buffer->overallOk());
    }

    public function test_aggregates_counts_across_results(): void
    {
        $buffer = new OrderedResultBuffer(2, new BufferedOutput());

        $buffer->record(new WorkerResult(0, 'phel.a', true, '', [], new Counts(pass: 4, total: 4)));
        $buffer->record(new WorkerResult(1, 'phel.b', false, '', ['phel.b/broken'], new Counts(pass: 2, failed: 1, total: 3)));

        $totals = $buffer->totals();
        self::assertSame(6, $totals->pass);
        self::assertSame(1, $totals->failed);
        self::assertSame(7, $totals->total);
    }

    public function test_aggregates_failed_tests_across_results(): void
    {
        $buffer = new OrderedResultBuffer(2, new BufferedOutput());

        $buffer->record(new WorkerResult(0, 'phel.a', false, '', ['phel.a/one', 'phel.a/two'], new Counts(failed: 2, total: 2)));
        $buffer->record(new WorkerResult(1, 'phel.b', false, '', ['phel.b/three'], new Counts(failed: 1, total: 1)));

        self::assertSame(
            ['phel.a/one', 'phel.a/two', 'phel.b/three'],
            $buffer->allFailedTests(),
        );
    }

    public function test_is_complete_only_when_all_slots_recorded(): void
    {
        $buffer = new OrderedResultBuffer(2, new BufferedOutput());

        self::assertFalse($buffer->isComplete());

        $buffer->record(new WorkerResult(0, 'phel.a', true, '', [], new Counts()));
        self::assertFalse($buffer->isComplete());

        $buffer->record(new WorkerResult(1, 'phel.b', true, '', [], new Counts()));
        self::assertTrue($buffer->isComplete());
    }

    public function test_record_crash_synthesises_a_failed_result(): void
    {
        $output = new BufferedOutput();
        $buffer = new OrderedResultBuffer(1, $output);

        $buffer->recordCrash(0, 'phel.bad', "boom\n");
        $buffer->finishProgress();

        self::assertTrue($buffer->isComplete());
        self::assertFalse($buffer->overallOk());
        self::assertSame(1, $buffer->totals()->error);
        self::assertStringContainsString('Worker died while running phel.bad', $output->fetch());
    }

    public function test_record_crash_with_null_index_is_a_no_op(): void
    {
        $buffer = new OrderedResultBuffer(1, new BufferedOutput());

        $buffer->recordCrash(null, 'phel.unknown', '');

        self::assertFalse($buffer->isComplete());
        self::assertTrue($buffer->overallOk());
        self::assertSame(0, $buffer->totals()->total);
    }
}
