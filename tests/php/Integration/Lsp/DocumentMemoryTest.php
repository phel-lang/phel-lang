<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lsp;

use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function dirname;

final class DocumentMemoryTest extends TestCase
{
    public function test_a_position_near_the_start_of_a_long_line_uses_bounded_memory(): void
    {
        $code = <<<'PHP'
require $argv[1];
$document = new Phel\Lsp\Application\Document\Document('file:///large.phel', 'phel', 1, str_repeat('é', 2000000));
echo $document->positionToOffset(['line' => 0, 'character' => 0]), ':', $document->positionToOffset(['line' => 0, 'character' => 1]);
PHP;
        $result = Subprocess::run([PHP_BINARY, '-d', 'memory_limit=64M', '-r', $code, dirname(__DIR__, 4) . '/vendor/autoload.php']);

        self::assertSame(0, $result->exitCode, $result->stderr);
        self::assertSame('0:2', $result->stdout);
    }
}
