<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Infrastructure\Daemon;

use Phel\Api\ApiFacade;
use Phel\Api\Infrastructure\Daemon\JsonRpcDispatcher;
use Phel\Shared\VersionResolver;
use PHPUnit\Framework\TestCase;

final class JsonRpcDispatcherVersionTest extends TestCase
{
    public function test_version_answers_with_the_running_phel_version(): void
    {
        $response = new JsonRpcDispatcher(new ApiFacade())->dispatch(['id' => 7, 'method' => 'version']);

        self::assertSame(['id' => 7, 'result' => VersionResolver::current()], $response);
    }

    public function test_version_ignores_params(): void
    {
        $response = new JsonRpcDispatcher(new ApiFacade())->dispatch([
            'id' => 'a',
            'method' => 'version',
            'params' => ['unused' => true],
        ]);

        self::assertSame(VersionResolver::current(), $response['result']);
    }
}
