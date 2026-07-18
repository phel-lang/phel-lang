<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Test;

use Phel\Shared\NamespaceInformation;

use function array_filter;
use function array_keys;
use function array_map;
use function array_shift;
use function array_values;
use function preg_match;
use function preg_quote;
use function str_replace;
use function str_starts_with;
use function strlen;

/**
 * Drops user namespaces no `--ns` glob touches and keeps the dependency
 * closure of the survivors. Bundled `phel.*` modules stay so fully qualified
 * references in surviving tests still resolve. Without this prune,
 * `bin/phel test --ns 'foo.*'` would still preload every other test
 * namespace before discarding their tests at selection time.
 */
final readonly class TestNamespacePruner
{
    /**
     * @param list<NamespaceInformation> $infos
     * @param list<string>               $patterns
     *
     * @return list<NamespaceInformation>
     */
    public function prune(array $infos, array $patterns): array
    {
        if ($patterns === []) {
            return $infos;
        }

        $regexes = array_map($this->globToRegex(...), $patterns);

        $byName = [];
        foreach ($infos as $info) {
            $byName[$info->getNamespace()] = $info;
        }

        $keep = [];
        foreach ($infos as $info) {
            $ns = $info->getNamespace();
            if (str_starts_with($ns, 'phel.') || $this->matchesAny($ns, $regexes)) {
                $keep[$ns] = true;
            }
        }

        $queue = array_keys($keep);
        while ($queue !== []) {
            $current = array_shift($queue);
            $info = $byName[$current] ?? null;
            if ($info === null) {
                continue;
            }

            foreach ($info->getDependencies() as $dep) {
                if (!isset($keep[$dep]) && isset($byName[$dep])) {
                    $keep[$dep] = true;
                    $queue[] = $dep;
                }
            }
        }

        return array_values(array_filter(
            $infos,
            static fn(NamespaceInformation $i): bool => isset($keep[$i->getNamespace()]),
        ));
    }

    /**
     * @param list<string> $regexes
     */
    private function matchesAny(string $namespace, array $regexes): bool
    {
        $normalised = str_replace('\\', '.', $namespace);
        return array_any($regexes, static fn(string $regex): bool => preg_match($regex, $normalised) === 1);
    }

    /**
     * Mirrors `phel\test\selector/glob->regex`: `*` matches one segment,
     * `**` matches any run, `.` is literal.
     */
    private function globToRegex(string $pattern): string
    {
        $normalised = str_replace('\\', '.', $pattern);
        $out = '';
        $len = strlen($normalised);
        $i = 0;
        while ($i < $len) {
            $ch = $normalised[$i];
            if ($ch === '*' && $i + 1 < $len && $normalised[$i + 1] === '*') {
                $out .= '.*';
                $i += 2;
                continue;
            }

            if ($ch === '*') {
                $out .= '[^.]*';
                ++$i;
                continue;
            }

            if ($ch === '.') {
                $out .= '\\.';
                ++$i;
                continue;
            }

            $out .= preg_quote($ch, '/');
            ++$i;
        }

        return '/^' . $out . '$/';
    }
}
