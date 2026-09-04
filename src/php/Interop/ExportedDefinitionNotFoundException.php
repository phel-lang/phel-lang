<?php

declare(strict_types=1);

namespace Phel\Interop;

use RuntimeException;

use function sprintf;

/**
 * Raised when a generated wrapper cannot resolve the definition it wraps.
 *
 * A wrapper class is plain PHP: it resolves its Phel definition from the
 * registry on first call and never loads anything itself. In a host process
 * that forgot to evaluate the namespace, the registry answers `null` and the
 * failure used to surface one frame later as `Value of type null is not
 * callable`, which names neither the namespace nor the fix. The two causes
 * need different fixes, so they get different messages.
 *
 * @internal the generated wrappers are the only place this is raised from
 */
final class ExportedDefinitionNotFoundException extends RuntimeException
{
    public static function namespaceNotLoaded(string $phelNamespace, string $definitionName): self
    {
        return new self(sprintf(
            'Cannot call "%s/%s": the namespace "%s" is not loaded in this process. '
            . 'A generated wrapper resolves a definition, it does not load it. Evaluate the namespace '
            . 'during boot, either by requiring the entry point that "phel build" wrote or by calling '
            . 'Phel::run($projectRootDir, "%s").',
            $phelNamespace,
            $definitionName,
            $phelNamespace,
            $phelNamespace,
        ));
    }

    public static function definitionMissing(string $phelNamespace, string $definitionName): self
    {
        return new self(sprintf(
            'Cannot call "%s/%s": the namespace "%s" is loaded but defines no "%s". '
            . 'The wrapper is stale if the function was renamed or removed; re-run "phel export".',
            $phelNamespace,
            $definitionName,
            $phelNamespace,
            $definitionName,
        ));
    }
}
