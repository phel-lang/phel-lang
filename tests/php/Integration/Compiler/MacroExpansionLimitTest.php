<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function dirname;

final class MacroExpansionLimitTest extends TestCase
{
    use RemoveDirTrait;

    public function test_self_expanding_macro_fails_at_the_original_call(): void
    {
        $directory = sys_get_temp_dir() . '/phel-macro-limit-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $source = $directory . '/recursive.phel';
        file_put_contents($source, "(ns audit.rec)\n(defmacro m [] (quote (m)))\n(println \"before\")\n(m)\n");
        try {
            // Cold stdlib compilation shares this watchdog with macro expansion.
            $result = Subprocess::run(
                [PHP_BINARY, '-d', 'max_execution_time=30', '-d', 'fatal_error_backtraces=0', '-d', 'memory_limit=128M', dirname(__DIR__, 4) . '/bin/phel', 'run', $source],
                env: [...getenv(), 'PHEL_CACHE_DIR' => $directory . '/cache', 'PHEL_NO_OPCACHE_REEXEC' => '1'],
            );
            $output = substr($result->stdout . $result->stderr, 0, 4096);
            self::assertNotSame(0, $result->exitCode);
            self::assertStringContainsString('[PHEL005]', $output);
            self::assertStringContainsString('recursive.phel:4', $output);
            self::assertStringContainsString('expansion depth', $output);
        } finally {
            $this->removeDir($directory);
        }
    }
}
