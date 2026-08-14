<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

use function basename;
use function class_exists;
use function dirname;
use function is_subclass_of;
use function strlen;
use function substr;

/**
 * Gacela 2 resolves explicit service maps without parsing source or docblocks.
 */
final class ExplicitServiceMapTest extends TestCase
{
    /**
     * @param class-string $pillarClass
     * @param class-string $expectedClass
     */
    #[DataProvider('pillarProvider')]
    public function test_module_pillar_declares_its_inherited_service(string $pillarClass, string $method, string $expectedClass): void
    {
        $matches = [];
        foreach (new ReflectionClass($pillarClass)->getAttributes(ServiceMap::class) as $attribute) {
            $serviceMap = $attribute->newInstance();
            if ($serviceMap->method === $method) {
                $matches[] = $serviceMap->className;
            }
        }

        self::assertSame(
            [$expectedClass],
            $matches,
            $pillarClass . ' must declare one explicit service map for ' . $method . '().',
        );
    }

    /**
     * @return Generator<string, array{class-string, string, class-string}>
     */
    public static function pillarProvider(): Generator
    {
        $srcDir = dirname(__DIR__, 4) . '/src/php';
        $pillars = [
            ['*Facade.php', AbstractFacade::class, 'getFactory', 'Facade', 'Factory'],
            ['*Factory.php', AbstractFactory::class, 'getConfig', 'Factory', 'Config'],
            ['*Provider.php', AbstractProvider::class, 'getConfig', 'Provider', 'Config'],
        ];

        foreach ($pillars as [$pattern, $parentClass, $method, $suffix, $targetSuffix]) {
            foreach (self::sourcesMatching($srcDir, $pattern) as $path) {
                $relative = substr($path, strlen($srcDir) + 1);
                $className = 'Phel\\' . substr($relative, 0, -4);
                $className = str_replace('/', '\\', $className);

                if (!is_subclass_of($className, $parentClass)) {
                    continue;
                }

                $expectedClass = substr($className, 0, -strlen($suffix)) . $targetSuffix;
                if (!class_exists($expectedClass)) {
                    $expectedClass = AbstractConfig::class;
                }

                yield basename($path, '.php') => [$className, $method, $expectedClass];
            }
        }
    }

    /**
     * Every file under `$srcDir` whose name matches `$pattern`, at any depth.
     * Gacela 2.0 resolves a pillar by filename suffix wherever it sits, so a
     * glob rooted at the module directory is narrower than the framework and
     * would leave a nested pillar unchecked (#3062).
     *
     * @return list<string>
     */
    private static function sourcesMatching(string $srcDir, string $pattern): array
    {
        $paths = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir)) as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths);

        return $paths;
    }
}
