<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\NamespaceInformation;

/**
 * @internal
 */
interface NamespaceExtractorInterface
{
    public function getNamespaceFromFile(string $path): NamespaceInformation;

    /**
     * A file whose `ns` form does not analyse is skipped, so one broken file
     * cannot stop every command that scans its directory. `$failOnInvalidNsForm`
     * rethrows it instead, for a build, which must not ship without the file.
     *
     * @param list<string> $directories
     *
     * @throws CompilerException with `$failOnInvalidNsForm`, located on the bad `ns` form
     *
     * @return list<NamespaceInformation>
     */
    public function getNamespacesFromDirectories(array $directories, bool $failOnInvalidNsForm = false): array;
}
