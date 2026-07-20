<?php

declare(strict_types=1);

namespace PhelTest\Unit\Config;

use Gacela\Framework\Bootstrap\SetupGacela;
use Phel\Config\PhelConfig;
use Phel\Phel;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * Gacela discovers app modules by `class_exists()`-walking the project root,
 * skipping only `vendor/`. In this repo that walk reaches `tests/` and dies
 * loading a PHPUnit class standalone, so `phel list:modules` and
 * `phel cache:warm` fatal (#2787).
 *
 * `appModulePaths` scopes that walk. The contract worth protecting is that it
 * is strictly opt-in: a project that does not set it must hand Gacela exactly
 * what it handed before.
 */
final class AppModulePathsTest extends TestCase
{
    public function test_it_defaults_to_empty_so_gacela_keeps_walking_the_whole_root(): void
    {
        self::assertSame([], new PhelConfig()->getAppModulePaths());
    }

    public function test_an_unset_value_leaves_gacela_on_its_own_default(): void
    {
        // The assertion that matters for "no behavior change for end-user
        // projects": what Gacela actually ends up with, not what we passed.
        $setup = SetupGacela::fromCallable(Phel::configFn());

        self::assertSame([], $setup->getAppModulePaths());
    }

    public function test_configured_paths_reach_gacela(): void
    {
        $setup = SetupGacela::fromCallable(Phel::configFn(['src/php']));

        self::assertSame(['src/php'], $setup->getAppModulePaths());
    }

    public function test_it_round_trips_through_the_immutable_builder(): void
    {
        $config = new PhelConfig()->withAppModulePaths(['src/php', 'lib']);

        self::assertSame(['src/php', 'lib'], $config->getAppModulePaths());
        self::assertSame(['src/php', 'lib'], $config->jsonSerialize()[PhelConfig::APP_MODULE_PATHS]);
    }

    public function test_it_survives_an_unrelated_wither(): void
    {
        // `with()` rebuilds the whole object from a property map, so a key
        // missing from that map silently resets on any unrelated update.
        $config = new PhelConfig()
            ->withAppModulePaths(['src/php'])
            ->withFormatDirs(['src']);

        self::assertSame(['src/php'], $config->getAppModulePaths());
    }

    public function test_this_repo_scopes_discovery_away_from_tests(): void
    {
        $config = require dirname(__DIR__, 4) . '/phel-config.php';

        self::assertInstanceOf(PhelConfig::class, $config);
        self::assertSame(['src/php'], $config->getAppModulePaths());
    }
}
