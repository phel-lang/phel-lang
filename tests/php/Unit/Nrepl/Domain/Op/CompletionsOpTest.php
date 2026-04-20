<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Op;

use Phel\Api\Transfer\CompletionResultTransfer;
use Phel\Nrepl\Application\Op\CompletionsOp;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Shared\Facade\ApiFacadeInterface;
use PHPUnit\Framework\TestCase;

final class CompletionsOpTest extends TestCase
{
    public function test_it_delegates_to_api_facade_complete_with_types(): void
    {
        $api = $this->createStub(ApiFacadeInterface::class);
        $api->method('replCompleteWithTypes')->willReturn([
            new CompletionResultTransfer('map', 'function'),
            new CompletionResultTransfer('map?', 'function'),
        ]);

        $op = new CompletionsOp($api);
        $responses = $op->handle(new OpRequest('completions', 'r1', null, [
            'op' => 'completions',
            'prefix' => 'ma',
        ]));

        self::assertCount(1, $responses);
        $completions = $responses[0]->payload['completions'];
        self::assertCount(2, $completions);
        self::assertSame(['candidate' => 'map', 'type' => 'function'], $completions[0]);
        self::assertContains('done', $responses[0]->payload['status']);
    }

    public function test_op_name_is_completions(): void
    {
        $op = new CompletionsOp($this->createStub(ApiFacadeInterface::class));
        self::assertSame('completions', $op->name());
    }
}
