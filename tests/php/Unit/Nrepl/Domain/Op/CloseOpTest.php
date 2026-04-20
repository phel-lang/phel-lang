<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Nrepl\Application\Op\CloseOp;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use PHPUnit\Framework\TestCase;

final class CloseOpTest extends TestCase
{
    public function test_it_closes_existing_session(): void
    {
        $registry = new SessionRegistry();
        $session = $registry->create();
        $op = new CloseOp($registry);

        $responses = $op->handle(new OpRequest('close', 'r1', $session->id, ['op' => 'close']));

        self::assertCount(1, $responses);
        self::assertContains('session-closed', $responses[0]->payload['status']);
        self::assertContains('done', $responses[0]->payload['status']);
        self::assertNull($registry->get($session->id));
    }

    public function test_it_reports_error_for_unknown_session(): void
    {
        $registry = new SessionRegistry();
        $op = new CloseOp($registry);

        $responses = $op->handle(new OpRequest('close', 'r1', 'no-such', ['op' => 'close']));

        self::assertCount(1, $responses);
        self::assertContains('error', $responses[0]->payload['status']);
        self::assertContains('unknown-session', $responses[0]->payload['status']);
    }

    public function test_op_name_is_close(): void
    {
        $op = new CloseOp(new SessionRegistry());
        self::assertSame('close', $op->name());
    }
}
