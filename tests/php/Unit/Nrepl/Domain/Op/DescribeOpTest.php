<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Nrepl\Application\Op\CloneOp;
use Phel\Nrepl\Application\Op\DescribeOp;
use Phel\Nrepl\Domain\Op\OpDispatcher;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Shared\Facade\RunFacadeInterface;
use PHPUnit\Framework\TestCase;

final class DescribeOpTest extends TestCase
{
    public function test_it_advertises_known_ops_and_versions(): void
    {
        $dispatcher = new OpDispatcher([new CloneOp(new SessionRegistry())]);

        $run = $this->createStub(RunFacadeInterface::class);
        $run->method('getVersion')->willReturn('v0.99.0');

        $op = new DescribeOp($dispatcher, $run);
        $dispatcher->register($op);

        $responses = $op->handle(new OpRequest('describe', 'r1', null, ['op' => 'describe']));

        self::assertCount(1, $responses);
        $payload = $responses[0]->payload;
        self::assertArrayHasKey('ops', $payload);
        self::assertArrayHasKey('clone', $payload['ops']);
        self::assertArrayHasKey('describe', $payload['ops']);
        self::assertSame('v0.99.0', $payload['versions']['phel']['version-string']);
        self::assertSame('0.1.0', $payload['versions']['nrepl']['version-string']);
    }
}
