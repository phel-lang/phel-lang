<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\DataReaders;

use Phel\Run\Infrastructure\Command\RunCommand;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use PhelTest\Integration\Util\DirectoryUtil;
use Symfony\Component\Console\Input\InputInterface;

use function ob_get_clean;
use function ob_start;
use function register_shutdown_function;

final class DataReadersAutoloadTest extends AbstractTestCommand
{
    private const string CACHE_DIR = __DIR__ . '/cache';

    protected function setUp(): void
    {
        $this->removeGeneratedCache();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->resetContainer();
        $this->removeGeneratedCache();
        $this->removeGeneratedCacheOnShutdown();
        parent::tearDown();
    }

    public function test_it_registers_tags_from_data_readers_file(): void
    {
        $output = $this->captureRunOutput(__DIR__ . '/Fixtures/consumer.phel');

        self::assertStringContainsString('HELLO', $output);
    }

    private function captureRunOutput(string $path): string
    {
        ob_start();
        new RunCommand()->run(
            $this->stubInput($path),
            $this->stubOutput(),
        );

        return ob_get_clean() ?: '';
    }

    private function removeGeneratedCache(): void
    {
        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    private function removeGeneratedCacheOnShutdown(): void
    {
        register_shutdown_function(static function (): void {
            DirectoryUtil::removeDir(self::CACHE_DIR);
        });
    }

    private function stubInput(string $path): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArgument')->willReturnCallback(
            static fn(string $name): string|array => match ($name) {
                'path' => $path,
                'argv' => [],
                default => '',
            },
        );

        return $input;
    }
}
