<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;
use Phel\Shared\ScalarCoercion;
use Phel\Shared\VersionFinder;

/**
 * @internal
 */
final class ApiConfig extends AbstractConfig
{
    /**
     * Directory names, relative to a workspace root, whose contents are never
     * project sources and must not be indexed.
     *
     * The build output is the one that matters: it holds copies of the very
     * `.phel` files it was compiled from, so indexing it makes navigation land
     * in `out/` instead of `src/` or `vendor/` (#3155). The name is read from
     * the project's own `PhelConfig` rather than assumed, since `destDir` is
     * configurable.
     *
     * `vendor` is deliberately absent: the core library lives there and
     * navigating into it is the point.
     *
     * @return list<string>
     */
    public function excludedIndexDirs(): array
    {
        /** @var array<string, mixed> $buildConfig */
        $buildConfig = $this->get(PhelConfig::BUILD_CONFIG, []);
        $destDir = ScalarCoercion::toString($buildConfig[PhelBuildConfig::DEST_DIR] ?? null, 'out');

        return array_values(array_unique(array_filter([
            $destDir,
            '.git',
            'node_modules',
        ])));
    }

    /**
     * @return list<string>
     */
    public static function allNamespaces(): array
    {
        return [
            'phel.async',
            'phel.base64',
            'phel.bench',
            'phel.cli',
            'phel.core',
            'phel.edn',
            'phel.html',
            'phel.http',
            'phel.json',
            'phel.match',
            'phel.mock',
            'phel.pprint',
            'phel.reader',
            'phel.reflect',
            'phel.repl',
            'phel.router',
            'phel.schema',
            'phel.string',
            'phel.test',
            'phel.test.gen',
            'phel.test.rose',
            'phel.test.selector',
            'phel.test.shrink',
            'phel.trace',
            'phel.transit',
            'phel.walk',
            'phel.watch',
        ];
    }

    public static function githubRef(): string
    {
        return VersionFinder::LATEST_VERSION;
    }
}
