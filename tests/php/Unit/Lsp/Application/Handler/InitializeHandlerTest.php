<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Handler;

use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Handler\InitializeHandler;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\NotificationSink;
use Phel\Shared\Api\ProjectIndex;
use Phel\Shared\Facade\ApiFacadeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InitializeHandlerTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function windows_workspaces(): iterable
    {
        $uri = 'file:///C:/Users/x/My%20Project';
        yield 'root uri' => [['rootUri' => $uri]];
        yield 'workspace folder' => [['workspaceFolders' => [['uri' => $uri]]]];
    }

    #[DataProvider('windows_workspaces')]
    public function test_workspace_index_uses_the_windows_drive_path(array $params): void
    {
        $api = $this->createMock(ApiFacadeInterface::class);
        $api->expects(self::once())->method('indexProject')
            ->with(['C:/Users/x/My Project'])
            ->willReturn(new ProjectIndex([], []));
        $session = new Session(new DocumentStore(), new class() implements NotificationSink {
            public function notify(string $method, array $params): void {}
        });

        new InitializeHandler($api, new UriConverter())->handle($params, $session);

        self::assertSame(['C:/Users/x/My Project'], $session->workspaceRoots());
    }
}
