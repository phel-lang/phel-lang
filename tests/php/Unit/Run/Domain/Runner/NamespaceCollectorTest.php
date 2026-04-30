<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Runner;

use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Run\Domain\Runner\NamespaceCollector;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use PHPUnit\Framework\TestCase;

use function in_array;

final class NamespaceCollectorTest extends TestCase
{
    public function test_seeds_canonical_dot_form_for_phel_test(): void
    {
        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $commandFacade = $this->createStub(CommandFacadeInterface::class);

        $buildFacade
            ->method('getNamespaceFromFile')
            ->willReturn(new NamespaceInformation('/src/app.phel', 'app.main', []));

        $buildFacade
            ->expects(self::once())
            ->method('getDependenciesForNamespace')
            ->with(self::anything(), self::callback(
                static fn(array $namespaces): bool => in_array('phel.test', $namespaces, true)
                    && !in_array('phel\\test', $namespaces, true),
            ))
            ->willReturn([]);

        new NamespaceCollector($buildFacade, $commandFacade)
            ->getDependenciesFromPaths(['/src/app.phel']);
    }
}
