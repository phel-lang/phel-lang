<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Config;

use Phel\Config\PhelConfig;
use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function bin2hex;
use function dirname;
use function file_put_contents;
use function getenv;
use function is_string;
use function json_decode;
use function mkdir;
use function random_bytes;
use function realpath;
use function str_replace;
use function sys_get_temp_dir;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

/**
 * Runs the real `bin/phel config --format=json`, because the env vars are
 * applied by the same process boot a normal command goes through (#3525).
 */
final class ConfigJsonEnvOverridesTest extends TestCase
{
    use RemoveDirTrait;

    private const string PROJECT = '{project}';

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = realpath(sys_get_temp_dir()) . '/phel-config-env-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir . '/src', 0o755, true);
        file_put_contents(
            $this->projectDir . '/phel-config.php',
            <<<'PHP'
                <?php

                return (new \Phel\Config\PhelConfig())
                    ->withSrcDirs(['src'])
                    ->withCacheDir('.phel/configured-cache')
                    ->withPhelDir('.configured-state')
                    ->withOptimizationLevel(1)
                    ->withWarnDeprecations(false);

                PHP,
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
    }

    public function test_without_env_vars_the_configured_values_are_reported(): void
    {
        $json = $this->configJson([]);

        self::assertSame('.phel/configured-cache', $json[PhelConfig::CACHE_DIR]);
        self::assertSame('.configured-state', $json[PhelConfig::PHEL_DIR]);
        self::assertSame(1, $json[PhelConfig::OPTIMIZATION_LEVEL]);
        self::assertFalse($json[PhelConfig::WARN_DEPRECATIONS]);
    }

    /**
     * @return iterable<string, array{string, string, string, mixed}>
     */
    public static function provideEnvOverrides(): iterable
    {
        yield 'PHEL_CACHE_DIR' => ['PHEL_CACHE_DIR', self::PROJECT . '/env-cache', PhelConfig::CACHE_DIR, self::PROJECT . '/env-cache'];
        yield 'PHEL_DIR' => ['PHEL_DIR', self::PROJECT . '/env-state', PhelConfig::PHEL_DIR, self::PROJECT . '/env-state'];
        yield 'PHEL_OPTIMIZATION_LEVEL' => ['PHEL_OPTIMIZATION_LEVEL', '2', PhelConfig::OPTIMIZATION_LEVEL, 2];
        yield 'PHEL_WARN_DEPRECATIONS' => ['PHEL_WARN_DEPRECATIONS', '1', PhelConfig::WARN_DEPRECATIONS, true];
    }

    #[DataProvider('provideEnvOverrides')]
    public function test_an_env_var_overrides_the_configured_value(
        string $envVar,
        string $envValue,
        string $key,
        mixed $expected,
    ): void {
        $json = $this->configJson([$envVar => $this->inProject($envValue)]);

        self::assertSame(is_string($expected) ? $this->inProject($expected) : $expected, $json[$key]);
    }

    public function test_the_warn_deprecations_flag_turns_the_reported_value_on(): void
    {
        $json = $this->configJson([], ['--warn-deprecations']);

        self::assertTrue($json[PhelConfig::WARN_DEPRECATIONS]);
    }

    public function test_every_key_of_the_config_model_is_reported_in_its_order(): void
    {
        $json = $this->configJson([]);

        self::assertSame(array_keys(new PhelConfig()->jsonSerialize()), array_keys($json));
    }

    private function inProject(string $value): string
    {
        return str_replace(self::PROJECT, $this->projectDir, $value);
    }

    /**
     * @param array<string, string> $env
     * @param list<string>          $leadingArgs
     *
     * @return array<string, mixed>
     */
    private function configJson(array $env, array $leadingArgs = []): array
    {
        $inherited = getenv();
        foreach (['PHEL_CACHE_DIR', 'PHEL_DIR', 'PHEL_OPTIMIZATION_LEVEL', 'PHEL_WARN_DEPRECATIONS'] as $name) {
            unset($inherited[$name]);
        }

        $process = Subprocess::run(
            [PHP_BINARY, dirname(__DIR__, 6) . '/bin/phel', ...$leadingArgs, 'config', '--format=json'],
            $this->projectDir,
            env: [...$inherited, ...$env],
        );

        self::assertSame(0, $process->exitCode, $process->stderr);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($process->stdout, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
