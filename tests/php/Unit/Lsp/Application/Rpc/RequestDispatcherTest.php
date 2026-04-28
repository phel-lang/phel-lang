<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Rpc;

use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Rpc\RequestDispatcher;
use Phel\Lsp\Application\Rpc\ResponseBuilder;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;
use Phel\Lsp\Domain\NotificationSink;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RequestDispatcherTest extends TestCase
{
    public function test_returns_invalid_request_error_when_method_missing_on_request(): void
    {
        $dispatcher = new RequestDispatcher(new ResponseBuilder());

        $response = $dispatcher->dispatch(['id' => 1], $this->newSession());

        self::assertSame(ResponseBuilder::INVALID_REQUEST, $response['error']['code'] ?? null);
        self::assertSame(1, $response['id'] ?? null);
    }

    public function test_ignores_missing_method_on_notification(): void
    {
        $dispatcher = new RequestDispatcher(new ResponseBuilder());

        $response = $dispatcher->dispatch([], $this->newSession());

        self::assertNull($response);
    }

    public function test_method_not_found_for_unknown_request_method(): void
    {
        $dispatcher = new RequestDispatcher(new ResponseBuilder());

        $response = $dispatcher->dispatch([
            'id' => 7,
            'method' => 'textDocument/unknownThing',
        ], $this->newSession());

        self::assertSame(ResponseBuilder::METHOD_NOT_FOUND, $response['error']['code'] ?? null);
        self::assertSame(7, $response['id'] ?? null);
    }

    public function test_silently_drops_unknown_notification(): void
    {
        $dispatcher = new RequestDispatcher(new ResponseBuilder());

        $response = $dispatcher->dispatch([
            'method' => '$/somethingNoOneKnowsAbout',
        ], $this->newSession());

        self::assertNull($response);
    }

    public function test_dispatches_params_default_to_empty_array_when_missing(): void
    {
        $captured = [];
        $handler = $this->newHandler('probe', isNotification: false, onHandle: static function (array $params) use (&$captured): string {
            $captured = $params;
            return 'ok';
        });

        $dispatcher = new RequestDispatcher(new ResponseBuilder());
        $dispatcher->register($handler);

        $response = $dispatcher->dispatch(['id' => 2, 'method' => 'probe'], $this->newSession());

        self::assertSame([], $captured);
        self::assertSame('ok', $response['result'] ?? null);
    }

    public function test_params_of_wrong_type_default_to_empty_array(): void
    {
        $captured = [];
        $handler = $this->newHandler('probe', isNotification: false, onHandle: static function (array $params) use (&$captured): string {
            $captured = $params;
            return 'ok';
        });

        $dispatcher = new RequestDispatcher(new ResponseBuilder());
        $dispatcher->register($handler);

        $response = $dispatcher->dispatch([
            'id' => 3,
            'method' => 'probe',
            'params' => 'not-an-array',
        ], $this->newSession());

        self::assertSame([], $captured);
        self::assertSame('ok', $response['result'] ?? null);
    }

    public function test_handler_exception_becomes_internal_error_for_request(): void
    {
        $handler = $this->newHandler('boom', isNotification: false, onHandle: static function (): never {
            throw new RuntimeException('fatal');
        });

        $dispatcher = new RequestDispatcher(new ResponseBuilder());
        $dispatcher->register($handler);

        $response = $dispatcher->dispatch(['id' => 10, 'method' => 'boom'], $this->newSession());

        self::assertSame(ResponseBuilder::INTERNAL_ERROR, $response['error']['code'] ?? null);
        self::assertSame('fatal', $response['error']['message'] ?? null);
    }

    public function test_handler_exception_dropped_silently_for_notification(): void
    {
        $handler = $this->newHandler('note', isNotification: true, onHandle: static function (): never {
            throw new RuntimeException('noisy');
        });

        $dispatcher = new RequestDispatcher(new ResponseBuilder());
        $dispatcher->register($handler);

        $response = $dispatcher->dispatch(['method' => 'note'], $this->newSession());

        self::assertNull($response);
    }

    public function test_notification_result_not_wrapped_in_response(): void
    {
        $handler = $this->newHandler('note', isNotification: true, onHandle: static fn(): string => 'ignored');

        $dispatcher = new RequestDispatcher(new ResponseBuilder());
        $dispatcher->register($handler);

        self::assertNull($dispatcher->dispatch(['method' => 'note'], $this->newSession()));
    }

    public function test_known_methods_returns_registered_names(): void
    {
        $dispatcher = new RequestDispatcher(new ResponseBuilder());
        $dispatcher->register($this->newHandler('a', false, static fn(): null => null));
        $dispatcher->register($this->newHandler('b', true, static fn(): null => null));

        $methods = $dispatcher->knownMethods();

        self::assertContains('a', $methods);
        self::assertContains('b', $methods);
        self::assertTrue($dispatcher->hasMethod('a'));
        self::assertFalse($dispatcher->hasMethod('missing'));
    }

    private function newSession(): Session
    {
        return new Session(new DocumentStore(), new class() implements NotificationSink {
            public function notify(string $method, array $params): void {}
        });
    }

    private function newHandler(string $method, bool $isNotification, callable $onHandle): HandlerInterface
    {
        return new readonly class($method, $isNotification, $onHandle) implements HandlerInterface {
            public function __construct(
                private string $methodName,
                private bool $notification,
                private mixed $onHandle,
            ) {}

            public function method(): string
            {
                return $this->methodName;
            }

            public function isNotification(): bool
            {
                return $this->notification;
            }

            public function handle(array $params, Session $session): mixed
            {
                return ($this->onHandle)($params, $session);
            }
        };
    }
}
