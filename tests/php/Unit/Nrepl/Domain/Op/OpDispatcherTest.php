<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Nrepl\Domain\Op\OpDispatcher;
use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use PHPUnit\Framework\TestCase;

final class OpDispatcherTest extends TestCase
{
    public function test_it_routes_to_the_registered_handler(): void
    {
        $handler = new class() implements OpHandlerInterface {
            public function name(): string
            {
                return 'ping';
            }

            public function handle(OpRequest $request): array
            {
                return [OpResponse::build($request->id, $request->session, ['pong' => true], ['done'])];
            }
        };

        $dispatcher = new OpDispatcher([$handler]);
        $responses = $dispatcher->dispatch(['op' => 'ping', 'id' => '1', 'session' => 's']);

        self::assertCount(1, $responses);
        self::assertSame('1', $responses[0]->payload['id']);
        self::assertSame('s', $responses[0]->payload['session']);
        self::assertTrue($responses[0]->payload['pong']);
        self::assertSame(['done'], $responses[0]->payload['status']);
    }

    public function test_it_reports_unknown_op(): void
    {
        $dispatcher = new OpDispatcher();
        $responses = $dispatcher->dispatch(['op' => 'no-such-op', 'id' => '7']);

        self::assertCount(1, $responses);
        self::assertContains('unknown-op', $responses[0]->payload['status']);
        self::assertContains('done', $responses[0]->payload['status']);
    }

    public function test_it_reports_missing_op_key(): void
    {
        $dispatcher = new OpDispatcher();
        $responses = $dispatcher->dispatch(['id' => '1']);

        self::assertCount(1, $responses);
        self::assertContains('invalid-op', $responses[0]->payload['status']);
    }

    public function test_it_reports_invalid_message(): void
    {
        $dispatcher = new OpDispatcher();
        $responses = $dispatcher->dispatch('not-a-dict');

        self::assertCount(1, $responses);
        self::assertContains('invalid-message', $responses[0]->payload['status']);
    }

    public function test_known_ops_returns_registered_names(): void
    {
        $handlerA = $this->createMockHandler('a');
        $handlerB = $this->createMockHandler('b');

        $dispatcher = new OpDispatcher([$handlerA, $handlerB]);

        self::assertSame(['a', 'b'], $dispatcher->knownOps());
    }

    private function createMockHandler(string $name): OpHandlerInterface
    {
        return new readonly class($name) implements OpHandlerInterface {
            public function __construct(private string $opName) {}

            public function name(): string
            {
                return $this->opName;
            }

            public function handle(OpRequest $request): array
            {
                return [];
            }
        };
    }
}
