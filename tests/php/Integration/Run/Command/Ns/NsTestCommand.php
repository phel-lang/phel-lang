<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Ns;

use Phel\Phel;
use Phel\Run\Infrastructure\Command\NsCommand;
use Phel\Run\RunFacadeInterface;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Symfony\Component\Console\Input\InputInterface;

final class NsTestCommand extends AbstractTestCommand
{
    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
    }

    public function test_output_loaded_namespaces(): void
    {
        $facade = self::createStub(RunFacadeInterface::class);
        $facade->method('getLoadedNamespaces')
            ->willReturn(['app\\foo', 'app\\bar']);

        $command = new class($facade) extends NsCommand {
            public function __construct(
                private readonly RunFacadeInterface $facade,
            ) {
                parent::__construct();
            }

            protected function getFacade(): RunFacadeInterface
            {
                return $this->facade;
            }
        };

        $this->expectOutputRegex('/app\\\\foo/');
        $this->expectOutputRegex('/app\\\\bar/');

        $command->run(
            self::createStub(InputInterface::class),
            $this->stubOutput(),
        );
    }
}
