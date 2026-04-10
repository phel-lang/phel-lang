<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

interface NamespaceExtractorInterface
{
    public function getNamespaceFromFile(string $path): NamespaceInformation;

    /**
     * @param list<string> $directories
     *
     * @return list<NamespaceInformation>
     */
    public function getNamespacesFromDirectories(array $directories): array;

    /**
     * Lightweight pre-scan: registers all declared namespace names from
     * source files on the Registry, so NsSymbol can distinguish
     * user-defined clojure\* namespaces from phel standard library references.
     *
     * @param list<string> $directories
     */
    public function preRegisterDeclaredNamespaces(array $directories): void;
}
