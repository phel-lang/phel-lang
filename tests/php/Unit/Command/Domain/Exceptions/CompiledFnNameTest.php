<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Domain\Exceptions;

use Phel;
use Phel\Command\Domain\Exceptions\CompiledFnName;
use Phel\Lang\AbstractFn;
use Phel\Lang\Registry;
use Phel\Shared\Munge;
use PHPUnit\Framework\TestCase;

final class CompiledFnNameTest extends TestCase
{
    protected function setUp(): void
    {
        Phel::clear();
    }

    public function test_names_the_fn_with_a_dot_namespace_and_a_slash(): void
    {
        $fn = new class() extends AbstractFn {
            public const BOUND_TO = 'my_app\\foo_bar\\add_it';

            public function __invoke(): mixed
            {
                return null;
            }
        };

        self::assertSame('my-app.foo-bar/add-it', $this->fnName()->displayName($fn::class));
    }

    public function test_takes_the_defined_name_from_the_registry(): void
    {
        $fn = new class() extends AbstractFn {
            public const BOUND_TO = 'app\\main\\snake_case';

            public function __invoke(): mixed
            {
                return null;
            }
        };
        Registry::getInstance()->addDefinition('app.main', 'snake_case', $fn);

        self::assertSame('app.main/snake_case', $this->fnName()->displayName($fn::class));
    }

    public function test_an_alias_bound_first_does_not_take_the_defining_name(): void
    {
        $fn = new class() extends AbstractFn {
            public const BOUND_TO = 'app\\main\\add_it';

            public function __invoke(): mixed
            {
                return null;
            }
        };
        Registry::getInstance()->addDefinition('app.main', 'plus', $fn);
        Registry::getInstance()->addDefinition('app.main', 'add-it', $fn);

        self::assertSame('app.main/add-it', $this->fnName()->displayName($fn::class));
    }

    public function test_an_alias_alone_falls_back_to_the_compiled_name(): void
    {
        $fn = new class() extends AbstractFn {
            public const BOUND_TO = 'app\\main\\add_it';

            public function __invoke(): mixed
            {
                return null;
            }
        };
        Registry::getInstance()->addDefinition('app.main', 'plus', $fn);

        self::assertSame('app.main/add-it', $this->fnName()->displayName($fn::class));
    }

    public function test_a_class_without_bound_to_has_no_name(): void
    {
        $fn = new class() extends AbstractFn {
            public function __invoke(): mixed
            {
                return null;
            }
        };

        self::assertNull($this->fnName()->displayName($fn::class));
    }

    private function fnName(): CompiledFnName
    {
        return new CompiledFnName(new Munge());
    }
}
