<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Diagnostics;

use Closure;
use Phel\Lint\LintFacade;
use Phel\Lsp\Application\Convert\DiagnosticConverter;
use Phel\Lsp\Application\Convert\UriConverter;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Domain\NotificationSink;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Facade\ApiFacadeInterface;
use Throwable;

/**
 * @internal
 */
final class DiagnosticPublisher
{
    /** @var array<string, array{document: Document, sink: NotificationSink, dueAt: float}> */
    private array $pending = [];

    /** @var Closure(): float */
    private readonly Closure $clock;

    public function __construct(
        private readonly ApiFacadeInterface $apiFacade,
        private readonly LintFacade $lintFacade,
        private readonly DiagnosticConverter $converter,
        private readonly int $debounceMs,
        ?Closure $clock = null,
        private readonly UriConverter $uris = new UriConverter(),
    ) {
        $this->clock = $clock ?? static fn(): float => hrtime(true) / 1_000_000;
    }

    public function schedule(Document $document, NotificationSink $sink): void
    {
        $this->pending[$document->uri] = [
            'document' => $document,
            'sink' => $sink,
            'dueAt' => ($this->clock)() + (float) $this->debounceMs,
        ];
    }

    public function publishDue(): void
    {
        $now = ($this->clock)();
        foreach ($this->pending as $pending) {
            if ($pending['dueAt'] <= $now) {
                $this->publish($pending['document'], $pending['sink']);
            }
        }
    }

    public function cancel(string $uri): void
    {
        unset($this->pending[$uri]);
    }

    public function publish(Document $document, NotificationSink $sink): void
    {
        $this->cancel($document->uri);
        $diagnostics = $this->apiFacade->analyzeSource($document->text, $document->uri);
        foreach ($this->lintDiagnostics($document) as $diagnostic) {
            $diagnostics[] = $diagnostic;
        }

        $params = $this->converter->toPublishParams($document->uri, $diagnostics);
        $params['version'] = $document->version;
        $sink->notify('textDocument/publishDiagnostics', $params);
    }

    public function publishNow(Document $document, NotificationSink $sink): void
    {
        $this->publish($document, $sink);
    }

    /**
     * @return list<Diagnostic>
     */
    private function lintDiagnostics(Document $document): array
    {
        try {
            $settings = $this->lintFacade->defaultSettings();

            return $this->lintFacade->lintSource($document->text, $this->uris->toFilePath($document->uri), $settings)->diagnostics;
        } catch (Throwable) {
            return [];
        }
    }
}
