<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

use function is_string;
use function sprintf;

/**
 * Detects a *call site* of a definition whose metadata carries
 * `:deprecated`, so a deprecated `def`/`defn` announces itself instead of
 * being discoverable only through `(doc ...)`.
 *
 * This is the generic mechanism behind every definition-level deprecation:
 * marking a definition is enough, no bespoke detector is needed. It works for
 * project code too — `^{:deprecated "1.4.0" :superseded-by "new-fn"}` on a
 * user `defn` warns at every call site.
 *
 * Recognised metadata keys:
 *
 * - `:deprecated` — required. A version string (`"0.32.0"`) is rendered as
 *   "since <version>"; any other string is rendered verbatim as the reason;
 *   `true` marks it deprecated with no further detail.
 * - `:superseded-by` — optional replacement name, rendered as "use X instead".
 *
 * Detection only: the enabled gate, the bundled-stdlib suppression, the
 * per-`(file, symbol)` dedup, and the macro-expansion attribution belong to
 * {@see DeprecationWarnings}.
 *
 * @internal
 */
final class DeprecatedDefinitionWarner
{
    private const string VERSION_PATTERN = '/^\d+\.\d+(\.\d+)?$/';

    private static ?self $instance = null;

    /**
     * Shared instance. Stateless, so it exists only to spare the analyzer a
     * per-resolution allocation; there is nothing to reset between runs.
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $definitionMeta the `def` metadata of the resolved definition
     */
    public function maybeWarn(string $ns, Symbol $name, PersistentMapInterface $definitionMeta): void
    {
        if (!DeprecationWarnings::isEnabled()) {
            return;
        }

        $deprecated = $definitionMeta->find(Keyword::create('deprecated'));
        if ($deprecated === null || $deprecated === false) {
            return;
        }

        $location = $name->getStartLocation();
        if (!$location instanceof SourceLocation) {
            return;
        }

        $fullName = $ns . '/' . $name->getName();
        DeprecationWarnings::warnOnceAtOrigin(
            $location,
            $fullName,
            fn(string $file, int $line): string => $this->buildMessage(
                $fullName,
                $file,
                $line,
                $deprecated,
                $definitionMeta,
            ),
        );
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $definitionMeta
     */
    private function buildMessage(
        string $fullName,
        string $file,
        int $line,
        mixed $deprecated,
        PersistentMapInterface $definitionMeta,
    ): string {
        return sprintf(
            "Definition '%s' used at %s:%d is deprecated%s.%s It will be removed in a future release.",
            $fullName,
            $file,
            $line,
            $this->detail($deprecated),
            $this->replacement($definitionMeta),
        );
    }

    private function detail(mixed $deprecated): string
    {
        if (!is_string($deprecated) || $deprecated === '') {
            return '';
        }

        return preg_match(self::VERSION_PATTERN, $deprecated) === 1
            ? sprintf(' (since %s)', $deprecated)
            : sprintf(': %s', $deprecated);
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $definitionMeta
     */
    private function replacement(PersistentMapInterface $definitionMeta): string
    {
        $supersededBy = $definitionMeta->find(Keyword::create('superseded-by'));

        if (!is_string($supersededBy) || $supersededBy === '') {
            return '';
        }

        return sprintf(" Use '%s' instead.", $supersededBy);
    }
}
