<?php

declare(strict_types=1);

namespace Phel\Run;

use Phel\Build\Domain\Extractor\NamespaceInformation;

interface RunFacadeInterface
{
    public function runNamespace(string $namespace): void;

    public function getNamespaceFromFile(string $fileOrPath): NamespaceInformation;

    /**
     * @return list<string>
     */
    public function getAllPhelDirectories(): array;

    /**
     * @param list<string> $directories
     * @param list<string> $ns
     *
     * @return list<NamespaceInformation>
     */
    public function getDependenciesForNamespace(array $directories, array $ns): array;

    public function evalFile(NamespaceInformation $info): void;
}
