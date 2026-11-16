<?php

declare(strict_types=1);

namespace Phel\Shared;

use Phel\Lang\LoadClasspath;

use function array_pop;
use function array_reverse;
use function dirname;
use function explode;
use function implode;
use function is_file;
use function str_starts_with;
use function substr;

/**
 * Finds the `.phel` file a `(load ...)` form pulls in, searching the
 * classpath roots and then the caller's directory, as the runtime does.
 *
 * @internal
 */
final readonly class LoadedSourceLocator
{
    public function __construct(
        private Munge $munge = new Munge(),
    ) {}

    public function locate(string $callerNamespace, string $pathArg, string $callerFile): ?string
    {
        $keys = str_starts_with($pathArg, '/')
            ? [substr($pathArg, 1)]
            : [$this->classpathDirOf($callerNamespace) . '/' . $pathArg, $pathArg];

        foreach (LoadClasspath::read() as $root) {
            $found = $this->firstExisting($root, $keys);
            if ($found !== null) {
                return $found;
            }
        }

        // Next to the caller the runtime tries the bare key first (LoadEmitter).
        return $this->firstExisting(dirname($callerFile), array_reverse($keys));
    }

    /**
     * @param list<string> $keys
     */
    private function firstExisting(string $root, array $keys): ?string
    {
        foreach ($keys as $key) {
            $candidate = $root . '/' . $key . '.phel';
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function classpathDirOf(string $namespace): string
    {
        $parts = explode('\\', $this->munge->encodePhpNs($namespace));
        array_pop($parts);

        return implode('/', $parts);
    }
}
