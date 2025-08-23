<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Ns;

use Phel;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Run\Infrastructure\Command\NsCommand;
use Phel\Run\RunFacadeInterface;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Symfony\Component\Console\Input\InputInterface;

final class NsCommandTest extends AbstractTestCommand
{
    public static function setUpBeforeClass(): void
    {
        Phel::bootstrap(__DIR__);
    }

    public function test_output_loaded_namespaces(): void
    {
        $facade = self::createStub(RunFacadeInterface::class);
        $facade->method('getLoadedNamespaces')
            ->willReturn([
                new NamespaceInformation(__FILE__, 'app\\foo', []),
                new NamespaceInformation(__FILE__, 'app\\bar', []),
            ]);

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

    public function test_output_namespace_dependencies(): void
    {
        $facade = self::createStub(RunFacadeInterface::class);
        $facade->method('getAllPhelDirectories')
            ->willReturn(['src']);
        $facade->method('getDependenciesForNamespace')
            ->willReturn([
                new NamespaceInformation('foo.phel', 'app\\foo', []),
                new NamespaceInformation('bar.phel', 'app\\bar', ['app\\foo']),
            ]);

        $command = new class($facade) extends NsCommand {
            public function __construct(private readonly RunFacadeInterface $facade)
            {
                parent::__construct();
            }

            protected function getFacade(): RunFacadeInterface
            {
                return $this->facade;
            }
        };

        $input = self::createStub(InputInterface::class);
        $input->method('getArgument')->willReturn('app\\bar');

        $this->expectOutputRegex('/Dependencies for namespace: app\\\\bar/');
        $this->expectOutputRegex('/1\) Namespace: app\\\\foo/');
        $this->expectOutputRegex('/Used by: app\\\\bar/');
        $this->expectOutputRegex('/Dependencies: app\\\\foo \(foo\.phel\)/');
        $this->expectOutputRegex('/2\) Namespace: app\\\\bar/');
        $this->expectOutputRegex('/File: bar\.phel/');

        $command->run(
            $input,
            $this->stubOutput(),
        );
    }
}
