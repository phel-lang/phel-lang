<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\SourceAnalyzerInterface;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Facade\RunFacadeInterface;

/**
 * @internal
 */
final class DaemonSourceAnalyzer implements SourceAnalyzerInterface
{
    /** @var array<string, string> */
    private array $unsavedSources = [];

    public function __construct(
        private readonly SourceAnalyzerInterface $analyzer,
        private readonly RunFacadeInterface $runFacade,
    ) {}

    /**
     * @return list<Diagnostic>
     */
    public function analyze(string $source, string $uri): array
    {
        clearstatcache(true);
        if (is_file($uri) && file_get_contents($uri) === $source) {
            unset($this->unsavedSources[$uri]);
            $this->runFacade->prepareSourceAnalysis();
            foreach ($this->unsavedSources as $bufferUri => $bufferSource) {
                $this->analyzer->analyze($bufferSource, $bufferUri);
            }
        } else {
            $this->unsavedSources[$uri] = $source;
        }

        return $this->analyzer->analyze($source, $uri);
    }
}
