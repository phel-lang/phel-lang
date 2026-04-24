<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

use function sprintf;
use function str_replace;
use function str_starts_with;
use function strtr;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * Emits `E_USER_DEPRECATED` warnings when user code uses `\` as the
 * namespace separator (e.g. `phel\core/map`, `\Phel\Lang\Foo`) so the
 * codebase can migrate to Clojure-compatible dot syntax ahead of the
 * backslash form being removed.
 *
 * Scope of detection is intentionally narrow: only symbols that pass
 * through `SymbolResolver::resolve()` — call sites and qualified refs.
 * `ns`, `:require`, `:use`, and related forms are tracked as follow-ups
 * in https://github.com/phel-lang/phel-lang/issues/1567.
 */
final class BackslashSeparatorDeprecator
{
    /** @var array<string, true> */
    private array $seen = [];

    /** @var callable(string): void */
    private $emitter;

    public function __construct(
        private readonly bool $enabled,
        ?callable $emitter = null,
    ) {
        $this->emitter = $emitter ?? static function (string $msg): void {
            @trigger_error($msg, E_USER_DEPRECATED);
        };
    }

    public function maybeWarn(Symbol $symbol): void
    {
        if (!$this->enabled) {
            return;
        }

        $location = $symbol->getStartLocation();
        if (!$location instanceof SourceLocation) {
            return;
        }

        $file = $location->getFile();
        if ($file === '' || $this->isPhelStdlibSource($file)) {
            return;
        }

        $original = $symbol->getFullName();
        if (!$this->containsBackslashSeparator($original)) {
            return;
        }

        $key = $file . '|' . $original;
        if (isset($this->seen[$key])) {
            return;
        }

        $this->seen[$key] = true;
        ($this->emitter)($this->buildMessage($original, $file, $location->getLine()));
    }

    private function containsBackslashSeparator(string $fullName): bool
    {
        return str_contains($fullName, '\\');
    }

    private function isPhelStdlibSource(string $file): bool
    {
        // Normalize separators so the check works on any OS and for both
        // the dev-repo layout and Composer-vendored installs.
        $normalized = strtr($file, '\\', '/');

        return str_contains($normalized, '/src/phel/')
            || str_ends_with($normalized, '/src/phel');
    }

    private function buildMessage(string $original, string $file, int $line): string
    {
        return sprintf(
            "Backslash ('\\') namespace separator in symbol '%s' at %s:%d is deprecated; "
            . "use dot ('.') instead — e.g. '%s'. "
            . 'The backslash form will be removed in a future release.',
            $original,
            $file,
            $line,
            $this->suggestion($original),
        );
    }

    private function suggestion(string $original): string
    {
        // Drop the leading backslash from class FQNs and convert all
        // remaining `\` separators to `.` to match Clojure syntax.
        $trimmed = str_starts_with($original, '\\') ? substr($original, 1) : $original;

        return str_replace('\\', '.', $trimmed);
    }
}
