<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lsp;

use Phel;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lint\LintFacade;
use Phel\Lsp\Application\Convert\DiagnosticConverter;
use Phel\Lsp\Application\Convert\PositionConverter;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Diagnostics\DiagnosticPublisher;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Application\Document\DocumentStore;
use Phel\Lsp\Application\Handler\DidCloseHandler;
use Phel\Lsp\Application\Rpc\ParamsExtractor;
use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\NotificationSink;
use Phel\Shared\Facade\ApiFacadeInterface;
use PHPUnit\Framework\TestCase;

final class DiagnosticPublisherTest extends TestCase
{
    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        GlobalEnvironmentSingleton::initializeNew();
    }

    public function test_a_burst_publishes_only_the_latest_document_after_its_deadline(): void
    {
        $now = 0.0;
        $uri = 'file:///project/buffer.phel';
        $latest = "(ns buffer)\n(def third 3)";
        $api = $this->createMock(ApiFacadeInterface::class);
        $api->expects(self::once())->method('analyzeSource')->with($latest, $uri)->willReturn([]);
        $publisher = new DiagnosticPublisher($api, new LintFacade(), new DiagnosticConverter(new PositionConverter(), new UriConverter()), 200, static function () use (&$now): float {
            return $now;
        });
        $notifications = [];
        $sink = $this->sink($notifications);
        $document = new Document($uri, 'phel', 0, '');
        foreach ([0.0, 50.0, 100.0] as $i => $time) {
            $now = $time;
            $document->update($i === 2 ? $latest : '(ns buffer)', $i + 1);
            $publisher->schedule($document, $sink);
        }

        $now = 299.0;
        $publisher->publishDue();
        self::assertSame([], $notifications);
        $now = 300.0;
        $publisher->publishDue();
        self::assertCount(1, $notifications);
        self::assertSame(3, $notifications[0]['version']);
        $now = 500.0;
        $publisher->publishDue();
        self::assertCount(1, $notifications);
    }

    public function test_forced_publish_and_close_cancel_queued_work(): void
    {
        $now = 0.0;
        $api = $this->createMock(ApiFacadeInterface::class);
        $api->expects(self::once())->method('analyzeSource')->willReturn([]);
        $publisher = new DiagnosticPublisher($api, new LintFacade(), new DiagnosticConverter(new PositionConverter(), new UriConverter()), 200, static function () use (&$now): float {
            return $now;
        });
        $notifications = [];
        $sink = $this->sink($notifications);
        $store = new DocumentStore();
        $document = $store->open('file:///project/cancel.phel', 'phel', 1, '(ns cancel)');
        $publisher->schedule($document, $sink);
        $publisher->publishNow($document, $sink);

        $now = 300.0;
        $publisher->publishDue();
        self::assertCount(1, $notifications);
        $publisher->schedule($document, $sink);
        $session = new Session($store, $sink);
        new DidCloseHandler(new ParamsExtractor(), $publisher)->handle(['textDocument' => ['uri' => $document->uri]], $session);
        $now = 600.0;
        $publisher->publishDue();
        self::assertCount(2, $notifications);
        self::assertSame([], $notifications[1]['diagnostics']);
        self::assertNull($store->get($document->uri));
    }

    public function test_documents_have_independent_pending_deadlines(): void
    {
        $now = 0.0;
        $api = $this->createMock(ApiFacadeInterface::class);
        $api->expects(self::exactly(2))->method('analyzeSource')->willReturn([]);
        $publisher = new DiagnosticPublisher($api, new LintFacade(), new DiagnosticConverter(new PositionConverter(), new UriConverter()), 200, static function () use (&$now): float {
            return $now;
        });
        $notifications = [];
        $sink = $this->sink($notifications);
        $publisher->schedule(new Document('file:///project/first.phel', 'phel', 1, '(ns first)'), $sink);
        $now = 50.0;
        $publisher->schedule(new Document('file:///project/second.phel', 'phel', 1, '(ns second)'), $sink);
        $now = 200.0;
        $publisher->publishDue();
        self::assertCount(1, $notifications);
        self::assertSame('file:///project/first.phel', $notifications[0]['uri']);
        $now = 250.0;
        $publisher->publishDue();
        self::assertCount(2, $notifications);
        self::assertSame('file:///project/second.phel', $notifications[1]['uri']);
    }

    private function sink(array &$notifications): NotificationSink
    {
        return new class($notifications) implements NotificationSink {
            public function __construct(private array &$notifications) {}

            public function notify(string $method, array $params): void
            {
                $this->notifications[] = $params;
            }
        };
    }
}
