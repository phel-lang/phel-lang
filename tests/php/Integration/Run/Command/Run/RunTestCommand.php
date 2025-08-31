<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Run\Infrastructure\Command\RunCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Symfony\Component\Console\Input\InputInterface;

final class RunTestCommand extends AbstractTestCommand
{
    public function __construct()
    {
        parent::__construct(self::class);
    }

    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php');
        });
    }

    public function test_file_not_found(): void
    {
        $this->expectOutputRegex('~No rendered output after running namespace: "non-existing-file.phel"~');

        $this->createRunCommand()->run(
            $this->stubInput('non-existing-file.phel'),
            $this->stubOutput(),
        );
    }

    public function test_run_by_namespace(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->createRunCommand()->run(
            $this->stubInput('test\\test-script'),
            $this->stubOutput(),
        );
    }

    public function test_run_by_filename(): void
    {
        $this->expectOutputRegex('~hello world~');

        $this->createRunCommand()->run(
            $this->stubInput(__DIR__ . '/Fixtures/test-script.phel'),
            $this->stubOutput(),
        );
    }

    public function test_run_by_filename_outside_config(): void
    {
        $tmpFile = __DIR__ . '/outside-script.phel';
        file_put_contents($tmpFile, "(ns outside\script)\n(php/print \"hello world\\n\")");

        $this->expectOutputRegex('~hello world~');

        $this->createRunCommand()->run(
            $this->stubInput($tmpFile),
            $this->stubOutput(),
        );

        unlink($tmpFile);
    }

    public function test_run_file_with_runtime_error(): void
    {
        $this->expectOutputRegex('~Cannot use object of type Phel\\\Lang\\\Keyword as array~');

        $this->createRunCommand()->run(
            $this->stubInput(__DIR__ . '/Fixtures/runtime-error.phel'),
            $this->stubOutput(),
        );
    }

    public function test_run_file_with_runtime_error_in_nested_require(): void
    {
        $this->expectOutputRegex('~Cannot use object of type Phel\\\Lang\\\Keyword as array~');

        $this->createRunCommand()->run(
            $this->stubInput(__DIR__ . '/Fixtures/nested/main-requiring-one.phel'),
            $this->stubOutput(),
        );
    }

    private function createRunCommand(): RunCommand
    {
        return new RunCommand();
    }

    private function stubInput(string $path): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturn($path);

        return $input;
    }
}
