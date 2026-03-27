<?php

declare(strict_types=1);

namespace PhelTest\Integration\Repl;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\CompileOptions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class LoadFileTest extends TestCase
{
    private string $tempFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        Phel::bootstrap(__DIR__);
        Phel::addDefinition('phel\\repl', 'src-dirs', [__DIR__ . '/../../../../src']);

        $build = new BuildFacade();
        $build->evalFile(__DIR__ . '/../../../../src/phel/core.phel');
        $build->evalFile(__DIR__ . '/../../../../src/phel/test.phel');
        $build->evalFile(__DIR__ . '/../../../../src/phel/repl.phel');
    }

    protected function tearDown(): void
    {
        if ($this->tempFile !== '' && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_load_file_evaluates_phel_source(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'phel_') . '.phel';
        file_put_contents($this->tempFile, '(+ 1 2)');

        $facade = new CompilerFacade();
        $result = $facade->eval(
            '(phel\\repl/load-file "' . addslashes($this->tempFile) . '")',
            new CompileOptions(),
        );

        self::assertSame(3, $result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_load_file_returns_last_expression(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'phel_') . '.phel';
        file_put_contents($this->tempFile, "(+ 1 1)\n(+ 2 3)");

        $facade = new CompilerFacade();
        $result = $facade->eval(
            '(phel\\repl/load-file "' . addslashes($this->tempFile) . '")',
            new CompileOptions(),
        );

        self::assertSame(5, $result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_load_file_returns_nil_for_nonexistent_file(): void
    {
        $facade = new CompilerFacade();
        $result = @$facade->eval(
            '(phel\\repl/load-file "/tmp/nonexistent_phel_file_' . uniqid() . '.phel")',
            new CompileOptions(),
        );

        self::assertNull($result);
    }
}
