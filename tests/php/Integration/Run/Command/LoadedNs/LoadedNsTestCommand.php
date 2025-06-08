<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\LoadedNs;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Run\Infrastructure\Command\LoadedNsCommand;
use Phel\Run\RunFacadeInterface;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Symfony\Component\Console\Input\InputInterface;

final class LoadedNsTestCommand extends AbstractTestCommand
{
    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__, GacelaConfig::defaultPhpConfig());
    }

    public function test_output_loaded_namespaces(): void
    {
        $facade = $this->createStub(RunFacadeInterface::class);
        $facade->method('getLoadedNamespaces')->willReturn(['app\\foo', 'app\\bar']);

        $command = new class($facade) extends LoadedNsCommand {
            public function __construct(private readonly RunFacadeInterface $facade)
            {
                parent::__construct();
            }

            protected function getFacade(): RunFacadeInterface
            {
                return $this->facade;
            }
        };

        $this->expectOutputRegex('/app\\\\foo/');
        $this->expectOutputRegex('/app\\\\bar/');

        $command->run($this->stubInput(), $this->stubOutput());
    }

    private function stubInput(): InputInterface
    {
        return $this->createStub(InputInterface::class);
    }
}
