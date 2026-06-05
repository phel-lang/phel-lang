<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Ns;

use Phel;
use Phel\Run\Infrastructure\Command\NsCommand;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Shared\NamespaceInformation;
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

        ob_start();
        $command->run(
            self::createStub(InputInterface::class),
            $this->stubOutput(),
        );
        $output = (string) ob_get_clean();

        self::assertMatchesRegularExpression('/app\.foo/', $output);
        self::assertMatchesRegularExpression('/app\.bar/', $output);
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

        ob_start();
        $command->run(
            $input,
            $this->stubOutput(),
        );
        $output = (string) ob_get_clean();

        self::assertMatchesRegularExpression('/Dependencies for namespace: app\.bar/', $output);
        self::assertMatchesRegularExpression('/1\) Namespace: app\.foo/', $output);
        self::assertMatchesRegularExpression('/2\) Namespace: app\.bar/', $output);
        self::assertMatchesRegularExpression('/Dependencies \(1\): app\.foo/', $output);
        self::assertMatchesRegularExpression('/File: bar\.phel/', $output);
    }
}
