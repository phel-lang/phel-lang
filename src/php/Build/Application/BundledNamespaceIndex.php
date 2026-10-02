<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\FrameworkNamespaces;
use Phel\Shared\Munge;

use function array_keys;
use function array_slice;
use function str_starts_with;

/**
 * Decides whether a require that matched no scanned source file still
 * resolves: a namespace already loaded, or a `phel.*` namespace (directly or
 * as the target of a `clojure.*` remap) that Phel or an installed Phel package
 * ships. The shipped set is discovered from the configured source and vendor
 * directories, not the caller's scan, which a vendored build leaves without
 * the stdlib.
 *
 * @internal
 */
final class BundledNamespaceIndex
{
    /** @var array<string, true>|null */
    private ?array $bundled = null;

    public function __construct(
        private readonly NamespaceExtractorInterface $namespaceExtractor,
        private readonly CommandFacadeInterface $commandFacade,
        private readonly CompilerFacadeInterface $compilerFacade,
    ) {}

    public function resolvesWithoutSource(string $namespace): bool
    {
        $namespace = Munge::canonicalNs($namespace);
        if (Phel::isNamespaceLoaded($namespace)) {
            return true;
        }

        $target = FrameworkNamespaces::clojureTarget($namespace) ?? $namespace;
        if (!FrameworkNamespaces::isPhel($target)) {
            return false;
        }

        return Phel::isNamespaceLoaded($target) || isset($this->bundled()[$target]);
    }

    /**
     * Null when the require still resolves: already loaded, or shipped by Phel
     * or an installed package.
     */
    public function unresolvedRequireMessage(string $required, string $requiring): ?string
    {
        return $this->resolvesWithoutSource($required)
            ? null
            : $this->missingNamespaceMessage($required, $requiring);
    }

    /**
     * @param list<string> $knownNamespaces namespaces the caller's own scan found
     */
    public function missingNamespaceMessage(string $required, string $requiring, array $knownNamespaces = []): string
    {
        $required = Munge::canonicalNs($required);
        $typed = FrameworkNamespaces::clojureTarget($required) ?? $required;

        $candidates = [...$knownNamespaces, ...array_keys($this->bundled())];
        $suggestions = $this->compilerFacade->findSimilarNames($typed, $candidates);

        return ExtractorException::missingRequiredNamespaceMessage(
            $required,
            Munge::canonicalNs($requiring),
            array_slice($suggestions, 0, 1),
        );
    }

    /**
     * @return array<string, true>
     */
    private function bundled(): array
    {
        if ($this->bundled !== null) {
            return $this->bundled;
        }

        $directories = [
            ...$this->commandFacade->getSourceDirectories(),
            ...$this->commandFacade->getVendorSourceDirectories(),
        ];

        $bundled = [];
        if ($directories !== []) {
            foreach ($this->namespaceExtractor->getNamespacesFromDirectories($directories) as $info) {
                $namespace = Munge::canonicalNs($info->getNamespace());
                if (str_starts_with($namespace, FrameworkNamespaces::PHEL_PREFIX)) {
                    $bundled[$namespace] = true;
                }
            }
        }

        return $this->bundled = $bundled;
    }
}
