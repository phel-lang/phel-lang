<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Nrepl\Application\Op\CloneOp;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use PHPUnit\Framework\TestCase;

final class CloneOpTest extends TestCase
{
    public function test_it_creates_a_new_session_and_echoes_its_id(): void
    {
        $registry = new SessionRegistry();
        $op = new CloneOp($registry);
        $responses = $op->handle(new OpRequest('clone', 'req-1', null, ['op' => 'clone']));

        self::assertCount(1, $responses);
        $payload = $responses[0]->payload;

        self::assertArrayHasKey('new-session', $payload);
        self::assertSame('req-1', $payload['id']);
        self::assertContains('done', $payload['status']);
        self::assertNotEmpty($registry->get($payload['new-session']));
    }

    public function test_op_name_is_clone(): void
    {
        $op = new CloneOp(new SessionRegistry());
        self::assertSame('clone', $op->name());
    }
}
