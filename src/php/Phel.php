<?php

declare(strict_types=1);

namespace Phel;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\MergedConfigCache;
use Gacela\Framework\Gacela;
use Phar;
use Phel\Config\PhelConfig;
use Phel\Config\ProjectLayout;
use Phel\Filesystem\FilesystemFacade;
use Phel\Run\RunFacade;
use Phel\Shared\PhelProjectDirectory;
use Phel\Shared\ScalarCoercion;
use RuntimeException;

use function dirname;
use function getcwd;
use function in_array;
use function is_array;
use function is_string;

/**
 * @internal use \Phel instead
 */
class Phel
{
    public const string PHEL_CONFIG_FILE_NAME = 'phel-config.php';

    private const string PHEL_CONFIG_LOCAL_FILE_NAME = 'phel-config-local.php';

    /**
     * Project-relative cache dir for Gacela's config/class caches.
     *
     * Gacela resolves a relative dir against the app root, so each project gets
     * its own cache. This matters since Gacela >= 1.15 persists the merged app
     * config (`gacela-merged-config.php`): an empty dir would resolve to the
     * shared `sys_get_temp_dir()`, leaking one project's `srcDirs`/config into
     * another (e.g. the PHAR running against a user project). Reuses Phel's
     * existing `.phel/cache` convention.
     *
     * Can be overridden with the env variable: GACELA_CACHE_DIR=/tmp...
     *
     * @see https://github.com/gacela-project/gacela/pull/322
     */
    private const string FILE_CACHE_DIR = '.phel/cache';

    private static ?PhelConfig $autoDetectedConfig = null;

    /**
     * Set up Phel runtime argv and program globals.
     * This normalizes argument handling so argv contains only user arguments.
     *
     * @param string       $program The script path or namespace being executed
     * @param list<string> $argv    User arguments (without script name)
     */
    public static function setupRuntimeArgs(string $program, array $argv): void
    {
        $GLOBALS['__phel_program'] = $program;
        $GLOBALS['__phel_argv'] = $argv;
    }

    /**
     * Get the current program (script path or namespace).
     */
    public static function getProgram(): string
    {
        return ScalarCoercion::toString($GLOBALS['__phel_program'] ?? null);
    }

    /**
     * Get user arguments (without script name).
     *
     * @return list<string>
     */
    public static function getArgv(): array
    {
        return ScalarCoercion::toStringList($GLOBALS['__phel_argv'] ?? null);
    }

    /**
     * @param list<string>|null $argv
     */
    public static function bootstrap(string $projectRootDir, ?array $argv = null): void
    {
        if ($argv !== null && $argv !== []) {
            self::updateGlobalArgv($argv);
        }

        if (str_starts_with(__FILE__, 'phar://')) {
            $projectRootDir = self::resolvePharProjectRoot();
        }

        // Zero-config support: auto-detect project structure if no config file exists
        $configPath = $projectRootDir . '/' . self::PHEL_CONFIG_FILE_NAME;
        if (!file_exists($configPath)) {
            self::$autoDetectedConfig = self::detectProjectStructure($projectRootDir);
        }

        Gacela::bootstrap($projectRootDir, self::configFn());

        self::refreshStaleMergedConfigCache();
        self::mirrorPhelDirToEnv();
    }

    /**
     * Auto-detect project structure and return a sensible default configuration.
     * This enables zero-config usage for projects following conventional layouts.
     */
    public static function detectProjectStructure(string $projectRootDir): PhelConfig
    {
        $config = new PhelConfig();

        // Single scan of top-level directory to minimize syscalls
        $topLevel = @scandir($projectRootDir) ?: [];
        $hasSrc = in_array('src', $topLevel, true);
        $hasTests = in_array('tests', $topLevel, true);
        $hasVendor = in_array('vendor', $topLevel, true);

        // Check for nested layout (src/phel, tests/phel) only if parent exists
        $hasSrcPhel = $hasSrc && is_dir($projectRootDir . '/src/phel');
        $hasTestsPhel = $hasTests && is_dir($projectRootDir . '/tests/phel');

        // Determine layout based on detected structure
        if ($hasSrcPhel || $hasTestsPhel) {
            $config = $config->withLayout(ProjectLayout::Nested);
        } elseif ($hasSrc || $hasTests) {
            $config = $config->withLayout(ProjectLayout::Flat);
        }

        if ($hasVendor) {
            return $config->withVendorDir('vendor');
        }

        return $config;
    }

    /**
     * Get the auto-detected config (for use by Gacela config provider).
     */
    public static function getAutoDetectedConfig(): ?PhelConfig
    {
        return self::$autoDetectedConfig;
    }

    /**
     * This function helps to unify the running execution for a custom phel project.
     *
     * @param list<string>|null $argv User arguments (not including program name)
     */
    public static function run(string $projectRootDir, string $namespace, ?array $argv = null): void
    {
        // Set up normalized runtime args (program + user-only argv)
        self::setupRuntimeArgs($namespace, $argv ?? []);

        self::bootstrap($projectRootDir);

        $runFacade = new RunFacade();
        $runFacade->runNamespace($namespace);

        Gacela::getRequired(FilesystemFacade::class)->clearAll();
    }

    /**
     * @return Closure(GacelaConfig):void
     */
    public static function configFn(): callable
    {
        return static function (GacelaConfig $config): void {
            $config->enableFileCache(self::FILE_CACHE_DIR);
            // If we have auto-detected config (no phel-config.php exists), use it
            $autoConfig = self::getAutoDetectedConfig();
            if ($autoConfig instanceof PhelConfig) {
                // Register the auto-detected config as inline config
                $config->addAppConfigKeyValues($autoConfig->jsonSerialize());
            } else {
                // Normal config file loading
                $config->addAppConfig(self::PHEL_CONFIG_FILE_NAME, self::PHEL_CONFIG_LOCAL_FILE_NAME);
            }
        };
    }

    /**
     * Reset the auto-detected config (useful for testing).
     */
    public static function resetAutoDetectedConfig(): void
    {
        self::$autoDetectedConfig = null;
    }

    /**
     * Invalidate Gacela's persisted merged-config cache when its inputs change.
     *
     * Gacela (>= 1.15) auto-warms the merged app config to
     * `gacela-merged-config.php` on the first bootstrap and then reloads it
     * unconditionally, with no freshness check. So editing `phel-config.php`
     * (or upgrading Phel to a build with new `PhelConfig` keys) would otherwise
     * be silently ignored until the cache file is deleted by hand.
     *
     * We fingerprint the cache inputs (the config files plus the config
     * data-model classes) and, when the fingerprint changes, clear the merged
     * cache and re-init so the current values take effect. When nothing changed
     * this is a handful of stat/hash calls and the cache is reused as intended.
     */
    private static function refreshStaleMergedConfigCache(): void
    {
        $config = Config::getInstance();
        $cache = self::mergedConfigCache($config->getCacheDir());

        $fingerprintFile = preg_replace('/\.php$/', '.fingerprint', $cache->filename());
        if ($fingerprintFile === null) {
            return;
        }

        $current = self::configCacheFingerprint($config->getAppRootDir());
        $stored = is_file($fingerprintFile) ? @file_get_contents($fingerprintFile) : null;
        if ($stored === $current) {
            return;
        }

        $cache->clear();
        $config->init();

        if (is_dir(dirname($fingerprintFile))) {
            @file_put_contents($fingerprintFile, $current);
        }
    }

    /**
     * Rebuild Gacela's merged-config cache handle from public API only, so we
     * can locate and clear it without touching `Config`'s `@internal` cache
     * helpers. Mirrors how Gacela constructs it: the resolved cache dir plus the
     * optional `APP_ENV` suffix.
     */
    private static function mergedConfigCache(string $cacheDir): MergedConfigCache
    {
        $env = getenv('APP_ENV');

        return new MergedConfigCache($cacheDir, is_string($env) ? $env : '');
    }

    /**
     * Content hash of everything that determines the merged config: the project
     * config files plus the config data-model classes (whose keys define the
     * wire format). Any change flips the hash and invalidates the cache.
     */
    private static function configCacheFingerprint(string $projectRootDir): string
    {
        $parts = [];

        foreach ([self::PHEL_CONFIG_FILE_NAME, self::PHEL_CONFIG_LOCAL_FILE_NAME] as $name) {
            $parts[] = $name . ':' . self::fileHash($projectRootDir . '/' . $name);
        }

        foreach (['PhelConfig.php', 'PhelBuildConfig.php', 'PhelExportConfig.php'] as $model) {
            $parts[] = $model . ':' . self::fileHash(__DIR__ . '/Config/' . $model);
        }

        return md5(implode('|', $parts));
    }

    /**
     * Stable content hash of a single cache-input file, or a placeholder when
     * it is absent or unreadable.
     */
    private static function fileHash(string $path): string
    {
        if (!is_file($path)) {
            return '-';
        }

        return md5_file($path) ?: '-';
    }

    /**
     * Mirror `PhelConfig::PHEL_DIR` (configured via `setPhelDir()` in
     * `phel-config.php`) into the `PHEL_DIR` env var so every consumer
     * — including CLI commands that don't read Gacela config directly —
     * sees one source of truth. Any pre-existing env value wins.
     */
    private static function mirrorPhelDirToEnv(): void
    {
        if (getenv(PhelProjectDirectory::DIR_ENV) !== false) {
            return;
        }

        $configured = ScalarCoercion::toString(
            Config::getInstance()->get(PhelConfig::PHEL_DIR, ''),
        );
        if ($configured === '') {
            return;
        }

        putenv(PhelProjectDirectory::DIR_ENV . '=' . $configured);
    }

    /**
     * Resolve the project root directory when running from a PHAR.
     * Priority: 1) CWD with config, 2) PHAR directory with config, 3) CWD (auto-detected).
     *
     * The fallback must never point inside the PHAR: PHAR archives are read-only,
     * and Gacela's config loader relies on `glob()` which does not match `phar://`
     * paths on most platforms, so a phar:// root silently loads zero config values
     * and every cache write targets the read-only archive.
     */
    private static function resolvePharProjectRoot(): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('Unable to determine current working directory.');
        }

        // Check CWD first
        if (file_exists($cwd . '/' . self::PHEL_CONFIG_FILE_NAME)) {
            return $cwd;
        }

        // Check PHAR's directory
        $pharDir = dirname(Phar::running(false));
        if (file_exists($pharDir . '/' . self::PHEL_CONFIG_FILE_NAME)) {
            return $pharDir;
        }

        // Fall back to CWD so auto-detected config kicks in (see configFn()).
        // The phar's own phel core library is still loaded via NamespacesLoader.
        return $cwd;
    }

    /**
     * @param list<string> $argv
     */
    private static function updateGlobalArgv(array $argv): void
    {
        $globalArgv = $GLOBALS['argv'] ?? [];
        if (!is_array($globalArgv)) {
            return;
        }

        foreach (array_filter($argv) as $value) {
            if (!in_array($value, $globalArgv, true)) {
                $globalArgv[] = $value;
            }
        }

        $GLOBALS['argv'] = $globalArgv;
    }
}
