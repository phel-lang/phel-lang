<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Domain\Analyzer\PhpClassLike;
use Phel\Lang\CopyLocationFromTrait;
use PhelTest\Support\Fixtures\PhpInterop\HoverContract;
use PhelTest\Support\Fixtures\PhpInterop\HoverEnum;
use PhelTest\Support\Fixtures\PhpInterop\HoverFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function interface_exists;
use function spl_autoload_register;
use function spl_autoload_unregister;

final class PhpClassLikeTest extends TestCase
{
    #[DataProvider('provideClassLikeNames')]
    public function test_it_accepts_every_class_like_kind(string $name): void
    {
        self::assertTrue(PhpClassLike::exists($name));
    }

    public static function provideClassLikeNames(): iterable
    {
        yield 'class' => [HoverFixture::class];
        yield 'interface' => [HoverContract::class];
        yield 'trait' => [CopyLocationFromTrait::class];
        yield 'enum' => [HoverEnum::class];
    }

    #[DataProvider('provideNamesThatAreNotClassLike')]
    public function test_it_rejects_a_name_php_knows_as_something_else(string $name): void
    {
        self::assertFalse(PhpClassLike::exists($name));
    }

    public static function provideNamesThatAreNotClassLike(): iterable
    {
        // The collision the predicate exists to settle: `PDO` is a class, an
        // all-caps global constant is not.
        yield 'global constant' => ['PHP_EOL'];
        yield 'function' => ['array_map'];
        yield 'unknown name' => ['PhelTest\\NoSuchSymbol'];
    }

    /**
     * Pins the "one autoload attempt, then non-autoloading probes" shape.
     * `class_exists()` never reports an interface, so the interface can only be
     * found because the class probe already gave the loader its one chance.
     */
    public function test_it_finds_an_interface_the_loader_defines_on_demand(): void
    {
        $name = 'PhelTest\\Unit\\Compiler\\Analyzer\\LazilyDefinedContract';
        $loader = static function (string $requested) use ($name): void {
            if ($requested === $name) {
                eval('namespace PhelTest\Unit\Compiler\Analyzer; interface LazilyDefinedContract {}');
            }
        };
        spl_autoload_register($loader);

        try {
            self::assertFalse(interface_exists($name, false));
            self::assertTrue(PhpClassLike::exists($name));
        } finally {
            spl_autoload_unregister($loader);
        }
    }
}
