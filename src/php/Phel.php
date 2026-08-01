<?php

declare(strict_types=1);

namespace Phel;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use Phar;
use Phel\Command\CommandFacade;
use Phel\Config\ConfigLoadException;
use Phel\Config\PhelConfig;
use Phel\Config\ProjectLayout;
use Phel\Config\StrictPhpConfigReader;
use Phel\Filesystem\FilesystemFacade;
use Phel\Run\RunFacade;
use Phel\Shared\PhelProjectDirectory;
use Phel\Shared\ProjectRootResolver;
use Phel\Shared\ScalarCoercion;
use RuntimeException;
use Throwable;

use function dirname;
use function getcwd;
use function in_array;
use function ini_get;
use function is_array;

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

        try {
            Gacela::bootstrap($projectRootDir, self::configFn(self::readAppModulePaths($configPath)));

            // Gacela >= 1.16 degrades its caches to in-memory in read-only
            // environments, so this never fatals; a pre-warmed cache in a
            // read-only dir still serves reads.
            self::mergedConfigCacheInvalidator()->refreshIfStale();

            // Forces the merged app config to materialize, so a broken
            // phel-config.php fails here (inside the guard) rather than later.
            self::mirrorPhelDirToEnv();
        } catch (Throwable $throwable) {
            throw ConfigLoadException::wrapIfConfigError($throwable, $configPath);
        }
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
        self::setupRuntimeArgs($namespace, $argv ?? []);

        self::bootstrap($projectRootDir);

        $runFacade = new RunFacade();
        $runFacade->runNamespace($namespace);

        Gacela::getRequired(FilesystemFacade::class)->clearAll();
    }

    /**
     * Reports an uncaught throwable through Phel's source maps, so a deployed
     * build names the `.phel` file, line and call form instead of the generated
     * PHP and its anonymous closures (#2922).
     *
     * Reporting follows PHP's own rules rather than inventing new ones: the
     * report goes to the error log when `log_errors` is on and to output only
     * when `display_errors` is on, which is what keeps a stack trace out of a
     * production response body. The process still exits `255`, the code PHP
     * uses for an uncaught exception.
     *
     * The reporter never replaces the exception it is reporting: if the trace
     * cannot be mapped, for any reason, the plain PHP rendering goes out
     * instead.
     */
    public static function installExceptionHandler(string $projectRootDir): void
    {
        set_exception_handler(static function (Throwable $throwable) use ($projectRootDir): void {
            $report = self::renderUncaught($throwable, $projectRootDir);

            if (self::iniEnabled('log_errors')) {
                // The log is a machine sink, so it gets the text without the
                // escapes a terminal would render. The `display_errors` write
                // below keeps them, and honours `NO_COLOR` like the rest.
                error_log(self::withoutAnsi($report));
            }

            if (self::iniEnabled('display_errors')) {
                // `php://stderr` rather than the `STDERR` constant, which only
                // the CLI SAPI defines, and rather than the response body,
                // which is where a web SAPI would put it.
                $stderr = fopen('php://stderr', 'w');
                if ($stderr !== false) {
                    fwrite($stderr, $report . PHP_EOL);
                    fclose($stderr);
                }
            }

            exit(255);
        });
    }

    /**
     * @param list<string> $appModulePaths
     *
     * @return Closure(GacelaConfig):void
     */
    public static function configFn(array $appModulePaths = []): callable
    {
        return static function (GacelaConfig $config) use ($appModulePaths): void {
            // Left unset when empty so Gacela keeps its own default rather than
            // us pinning an equivalent-but-explicit value (#2787).
            if ($appModulePaths !== []) {
                $config->setAppModulePaths($appModulePaths);
            }

            // Gacela >= 1.16 keeps this cache in-memory when the dir is not
            // writable (read-only sandboxes) instead of fataling on `mkdir`,
            // and still serves reads from a pre-warmed cache dir.
            $config->enableFileCache(self::FILE_CACHE_DIR);

            // If we have auto-detected config (no phel-config.php exists), use it
            $autoConfig = self::getAutoDetectedConfig();
            if ($autoConfig instanceof PhelConfig) {
                // Register the auto-detected config as inline config
                $config->addAppConfigKeyValues($autoConfig->jsonSerialize());
            } else {
                // Normal config file loading. The strict reader rejects a
                // null-returning phel-config.php instead of letting Gacela
                // silently treat it as an empty config (#2642).
                $config->addAppConfig(
                    self::PHEL_CONFIG_FILE_NAME,
                    self::PHEL_CONFIG_LOCAL_FILE_NAME,
                    new StrictPhpConfigReader(),
                );
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
     * Gacela needs the app-module paths while bootstrapping, before it reads
     * `phel-config.php`, so the value is fetched up front rather than through
     * the merged config.
     *
     * Any failure yields `[]`, which is Gacela's own default (walk the whole
     * project root). That is not a swallowed error: the same file is evaluated
     * again inside {@see bootstrap()}'s try block, and the second evaluation
     * raises the same failure. Three properties make that hold, each pinned by
     * a test so a future change cannot quietly turn this into a real swallow:
     *
     * 1. {@see StrictPhpConfigReader::read()} uses plain `include`, not
     *    `include_once`. PHP marks a file as included before executing it, so
     *    only the `_once` forms would make the second evaluation a no-op. The
     *    plain form re-parses and re-runs the file, so the same ParseError, the
     *    same exception thrown by the file, or the same non-config return value
     *    is raised again.
     * 2. Gacela materializes its merged config lazily, so {@see bootstrap()}
     *    calls {@see mirrorPhelDirToEnv()} inside the guard to force it. Without
     *    that read the reader could never run at all.
     * 3. A warm merged-config cache does not hide it either: the edited
     *    `phel-config.php` is newer than the cache, so
     *    {@see mergedConfigCacheInvalidator()} re-inits and the reader runs.
     *
     * {@see ConfigLoadException::wrapIfConfigError()} then names the file and
     * `bin/phel` prints it and exits 1.
     *
     * @return list<string>
     */
    private static function readAppModulePaths(string $configPath): array
    {
        if (self::$autoDetectedConfig instanceof PhelConfig) {
            return self::$autoDetectedConfig->getAppModulePaths();
        }

        if (!file_exists($configPath)) {
            return [];
        }

        try {
            $config = require $configPath;
        } catch (Throwable) {
            // Deliberately not reported here: bootstrap() re-evaluates the file
            // and reports the same failure with the file name attached. See the
            // doc block above for why the second evaluation always happens.
            return [];
        }

        return $config instanceof PhelConfig ? $config->getAppModulePaths() : [];
    }

    private static function withoutAnsi(string $text): string
    {
        return ScalarCoercion::toString(preg_replace('/\033\[[0-9;]*m/', '', $text), $text);
    }

    /**
     * An ini flag PHP reports as a string: `"0"` and `"off"` are off, and
     * `display_errors` additionally takes `"stderr"` / `"stdout"`, which
     * `filter_var()`'s boolean filter would read as off.
     */
    private static function iniEnabled(string $option): bool
    {
        $value = strtolower(trim(ScalarCoercion::toString(ini_get($option), '')));

        return !in_array($value, ['', '0', 'off', 'false', 'no'], true);
    }

    private static function renderUncaught(Throwable $throwable, string $projectRootDir): string
    {
        try {
            return new CommandFacade()->getStackTraceString($throwable);
        } catch (Throwable) {
            // Not bootstrapped: a generated entry point calls only
            // `setupRuntimeArgs()`, so the facade has no container to resolve
            // from yet. Booting inside the handler keeps the caller's own
            // bootstrap optional.
        }

        try {
            self::bootstrap($projectRootDir);

            return new CommandFacade()->getStackTraceString($throwable);
        } catch (Throwable) {
            // A reporter that throws would replace the exception it exists to
            // report, which is the failure this whole handler is here to avoid.
            return (string) $throwable;
        }
    }

    /**
     * Build the merged-config cache invalidator for the current Gacela config,
     * wiring it to the project config files and the config data-model classes
     * whose contents define the cached merged config.
     */
    private static function mergedConfigCacheInvalidator(): MergedConfigCacheInvalidator
    {
        $config = Config::getInstance();
        $appRootDir = $config->getAppRootDir();

        return new MergedConfigCacheInvalidator(
            $config->getCacheDir(),
            $appRootDir,
            [
                $appRootDir . '/' . self::PHEL_CONFIG_FILE_NAME,
                $appRootDir . '/' . self::PHEL_CONFIG_LOCAL_FILE_NAME,
                __DIR__ . '/Config/PhelConfig.php',
                __DIR__ . '/Config/PhelBuildConfig.php',
                __DIR__ . '/Config/PhelExportConfig.php',
            ],
            static function () use ($config): void {
                $config->init();
            },
        );
    }

    /**
     * Mirror `PhelConfig::PHEL_DIR` (configured via `withPhelDir()` in
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

        // Prefer the user's project: walk up from CWD to the nearest phel-config.php.
        $root = ProjectRootResolver::resolveFromCwd($cwd);
        if (file_exists($root . '/' . self::PHEL_CONFIG_FILE_NAME)) {
            return $root;
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
