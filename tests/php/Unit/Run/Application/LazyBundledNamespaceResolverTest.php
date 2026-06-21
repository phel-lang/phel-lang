<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application;

use Phel\Build\Domain\Compile\CompiledFile;
use Phel\Run\Application\LazyBundledNamespaceResolver;
use Phel\Run\Application\NamespaceFileTracker;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;

final class LazyBundledNamespaceResolverTest extends TestCase
{
    protected function setUp(): void
    {
        NamespaceFileTracker::reset();
    }

    protected function tearDown(): void
    {
        NamespaceFileTracker::reset();
    }

    public function test_loads_bundled_namespace_closure_on_demand(): void
    {
        $coreInfo = new NamespaceInformation('/phel/core.phel', 'phel.core', [], true);
        $jsonInfo = new NamespaceInformation('/phel/json.phel', 'phel.json', ['phel.core'], true);

        $evalled = [];
        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $buildFacade->method('getDependenciesForNamespace')->willReturn([$coreInfo, $jsonInfo]);
        $buildFacade->method('evalFile')->willReturnCallback(
            static function (string $file) use (&$evalled): CompiledFile {
                $evalled[] = $file;
                return new CompiledFile($file, '', '', false);
            },
        );

        $resolver = new LazyBundledNamespaceResolver(
            $buildFacade,
            ['phel.core', 'phel.json'],
            ['/src'],
            new NamespaceFileTracker(),
        );

        self::assertTrue($resolver->resolveBundledNamespace('phel.json'));
        self::assertSame(['/phel/core.phel', '/phel/json.phel'], $evalled);
    }

    public function test_normalizes_backslash_separator(): void
    {
        $jsonInfo = new NamespaceInformation('/phel/json.phel', 'phel.json', [], true);

        $observedSeeds = [];
        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $buildFacade->method('getDependenciesForNamespace')->willReturnCallback(
            static function (array $dirs, array $seeds) use (&$observedSeeds, $jsonInfo): array {
                $observedSeeds[] = $seeds;
                return [$jsonInfo];
            },
        );
        $buildFacade->method('evalFile')->willReturn(new CompiledFile('', '', '', false));

        $resolver = new LazyBundledNamespaceResolver(
            $buildFacade,
            ['phel.json'],
            ['/src'],
            new NamespaceFileTracker(),
        );

        self::assertTrue($resolver->resolveBundledNamespace('phel\\json'));
        self::assertSame([['phel.json']], $observedSeeds);
    }

    public function test_returns_false_for_non_bundled_namespace(): void
    {
        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $buildFacade->expects(self::never())->method('getDependenciesForNamespace');
        $buildFacade->expects(self::never())->method('evalFile');

        $resolver = new LazyBundledNamespaceResolver(
            $buildFacade,
            ['phel.core', 'phel.json'],
            ['/src'],
            new NamespaceFileTracker(),
        );

        self::assertFalse($resolver->resolveBundledNamespace('app.core'));
    }

    public function test_does_not_re_evaluate_already_loaded_files(): void
    {
        $jsonInfo = new NamespaceInformation('/phel/json.phel', 'phel.json', [], true);

        $evalled = [];
        $buildFacade = $this->createMock(BuildFacadeInterface::class);
        $buildFacade->method('getDependenciesForNamespace')->willReturn([$jsonInfo]);
        $buildFacade->method('evalFile')->willReturnCallback(
            static function (string $file) use (&$evalled): CompiledFile {
                $evalled[] = $file;
                return new CompiledFile($file, '', '', false);
            },
        );

        $tracker = new NamespaceFileTracker();
        $tracker->markLoaded('/phel/json.phel');

        $resolver = new LazyBundledNamespaceResolver(
            $buildFacade,
            ['phel.json'],
            ['/src'],
            $tracker,
        );

        self::assertFalse(
            $resolver->resolveBundledNamespace('phel.json'),
            'A namespace whose files are all already loaded reports nothing new to load',
        );
        self::assertSame([], $evalled);
    }
}
