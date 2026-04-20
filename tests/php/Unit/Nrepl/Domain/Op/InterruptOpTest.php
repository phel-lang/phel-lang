<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Nrepl\Application\Op\InterruptOp;
use Phel\Nrepl\Domain\Op\OpRequest;
use PHPUnit\Framework\TestCase;

final class InterruptOpTest extends TestCase
{
    public function test_it_acknowledges_the_request(): void
    {
        $op = new InterruptOp();
        $responses = $op->handle(new OpRequest('interrupt', 'r1', 's1', ['op' => 'interrupt']));

        self::assertCount(1, $responses);
        self::assertContains('done', $responses[0]->payload['status']);
        self::assertContains('session-idle', $responses[0]->payload['status']);
    }

    public function test_op_name_is_interrupt(): void
    {
        self::assertSame('interrupt', (new InterruptOp())->name());
    }
}
