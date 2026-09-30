<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Diagnostic;

use Phel\Compiler\Domain\Diagnostic\WorkingDirectoryPaths;
use PHPUnit\Framework\TestCase;

use function chdir;
use function dirname;
use function getcwd;

final class WorkingDirectoryPathsTest extends TestCase
{
    private string $previousCwd;

    private string $cwd;

    protected function setUp(): void
    {
        $this->previousCwd = (string) getcwd();
        $this->cwd = dirname(__DIR__);
        chdir($this->cwd);
    }

    protected function tearDown(): void
    {
        chdir($this->previousCwd);
    }

    public function test_a_path_under_the_working_directory_is_shown_relative(): void
    {
        self::assertSame(
            'symbol at src/main.phel:1; macro at (src/lib.phel:3)',
            WorkingDirectoryPaths::shorten(
                'symbol at ' . $this->cwd . '/src/main.phel:1; macro at (' . $this->cwd . '/src/lib.phel:3)',
            ),
        );
    }

    public function test_a_path_outside_the_working_directory_stays_absolute(): void
    {
        $sibling = $this->cwd . '-other/src/main.phel:1';
        $nested = '/elsewhere' . $this->cwd . '/src/main.phel:1';

        self::assertSame('at ' . $sibling, WorkingDirectoryPaths::shorten('at ' . $sibling));
        self::assertSame('at ' . $nested, WorkingDirectoryPaths::shorten('at ' . $nested));
    }
}
