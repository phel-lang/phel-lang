<?php

declare(strict_types=1);

namespace PhelTest\Integration\Console;

use PhelTest\Support\Subprocess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function getenv;
use function mkdir;
use function random_bytes;
use function realpath;
use function sys_get_temp_dir;
use function trim;

use const PHP_BINARY;

/**
 * Phel's environment switches share one reading: `1/true/yes/on` and
 * `0/false/no/off` in any case, an empty value counts as unset, and anything
 * else stops the command with exit 2 naming the variable (#3525).
 */
final class EnvVarParsingTest extends TestCase
{
    private const string NOTICE = "Definition 'phel.core/to-php-array' used at";

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = realpath(sys_get_temp_dir()) . '/phel-env-vars-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0755, true);
        file_put_contents($this->dir . '/ok.phel', "(ns app.ok)\n(println 1)\n");
        file_put_contents(
            $this->dir . '/dep.phel',
            "(ns app.dep)\n(println (php/count (to-php-array [1 2])))\n",
        );
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideBadValues(): iterable
    {
        yield 'boolean' => [
            'PHEL_WARN_DEPRECATIONS',
            'maybe',
            'PHEL_WARN_DEPRECATIONS must be one of 1, true, yes, on, 0, false, no or off, got "maybe".',
        ];
        yield 'number' => [
            'PHEL_TEST_WORKERS',
            'abc',
            'PHEL_TEST_WORKERS must be a whole number of at least 1, got "abc".',
        ];
        yield 'boolean read before the console starts' => [
            'PHEL_OPCACHE_REEXEC',
            'sometimes',
            'PHEL_OPCACHE_REEXEC must be one of 1, true, yes, on, 0, false, no or off, got "sometimes".',
        ];
    }

    #[DataProvider('provideBadValues')]
    public function test_a_bad_value_exits_2_naming_the_variable(string $name, string $value, string $message): void
    {
        $result = $this->phel(['run', 'ok.phel'], [$name => $value]);

        self::assertSame(2, $result->exitCode, $result->stderr . $result->stdout);
        self::assertSame('', $result->stdout);
        self::assertSame($message, trim($result->stderr));
    }

    public function test_false_keeps_deprecation_notices_off(): void
    {
        $off = $this->phel(['run', 'dep.phel'], ['PHEL_WARN_DEPRECATIONS' => 'false']);
        $on = $this->phel(['run', 'dep.phel'], ['PHEL_WARN_DEPRECATIONS' => ' TRUE ']);

        self::assertSame(0, $off->exitCode, $off->stderr);
        self::assertStringNotContainsString(self::NOTICE, $off->stderr . $off->stdout);
        self::assertSame(0, $on->exitCode, $on->stderr);
        self::assertStringContainsString(self::NOTICE, $on->stderr . $on->stdout);
    }

    /**
     * @param list<string>          $args
     * @param array<string, string> $env
     */
    private function phel(array $args, array $env): Subprocess
    {
        return Subprocess::run(
            [PHP_BINARY, dirname(__DIR__, 4) . '/bin/phel', ...$args],
            $this->dir,
            env: [...getenv(), ...$env],
        );
    }
}
