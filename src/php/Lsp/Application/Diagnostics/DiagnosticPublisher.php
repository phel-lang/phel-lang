<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Diagnostics;

use Phel\Api\ApiFacade;
use Phel\Api\Transfer\Diagnostic;
use Phel\Lint\LintFacade;
use Phel\Lsp\Application\Convert\DiagnosticConverter;
use Phel\Lsp\Application\Document\Document;
use Phel\Lsp\Domain\NotificationSink;
use Throwable;

use function microtime;

/**
 * Runs Api semantic analysis + Lint rules on a document and pushes
 * `textDocument/publishDiagnostics` through the notification sink.
 *
 * Debouncing is cooperative: callers check `shouldPublish` before invoking
 * `publish`. The default window is 200ms.
 */
final class DiagnosticPublisher
{
    /** @var array<string, float> */
    private array $lastRunAt = [];

    public function __construct(
        private readonly ApiFacade $apiFacade,
        private readonly LintFacade $lintFacade,
        private readonly DiagnosticConverter $converter,
        private readonly int $debounceMs,
    ) {}

    public function shouldPublish(string $uri): bool
    {
        $last = $this->lastRunAt[$uri] ?? 0.0;
        $nowMs = microtime(true) * 1000.0;

        return ($nowMs - $last) >= $this->debounceMs;
    }

    public function publish(Document $document, NotificationSink $sink): void
    {
        $this->lastRunAt[$document->uri] = microtime(true) * 1000.0;

        $diagnostics = $this->apiFacade->analyzeSource($document->text, $document->uri);

        foreach ($this->lintDiagnostics($document) as $diagnostic) {
            $diagnostics[] = $diagnostic;
        }

        $params = $this->converter->toPublishParams($document->uri, $diagnostics);
        $sink->notify('textDocument/publishDiagnostics', $params);
    }

    /**
     * Force a publish (skipping debounce), used on didSave when the editor
     * expects an immediate refresh.
     */
    public function publishNow(Document $document, NotificationSink $sink): void
    {
        $this->lastRunAt[$document->uri] = 0.0;
        $this->publish($document, $sink);
    }

    /**
     * @return list<Diagnostic>
     */
    private function lintDiagnostics(Document $document): array
    {
        try {
            $settings = $this->lintFacade->defaultSettings();
            $result = $this->lintFacade->lint([$document->uri], $settings);

            return $result->diagnostics;
        } catch (Throwable) {
            // Lint on a single in-memory path may fail when the buffer isn't on disk;
            // we only use Lint as a best-effort augmentation.
            return [];
        }
    }
}
