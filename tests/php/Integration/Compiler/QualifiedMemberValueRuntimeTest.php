<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Shared\CompileOptions;
use PhelTest\Support\Fixtures\PhpInterop\QualifiedMemberFixture;

use function sprintf;

final class QualifiedMemberValueRuntimeTest extends AbstractCompilerRuntimeTestCase
{
    private const string FIXTURE = '\\' . QualifiedMemberFixture::class;

    public function test_a_static_method_is_usable_as_a_value(): void
    {
        $result = $this->compilerFacade->eval(
            sprintf('(to-php-array (map %s/upper ["a" "b"]))', self::FIXTURE),
            new CompileOptions(),
        );

        self::assertSame(['A', 'B'], $result);
    }

    public function test_an_instance_method_is_a_fn_of_the_receiver(): void
    {
        $result = $this->compilerFacade->eval(
            sprintf(
                '(to-php-array (map %s/.label [(php/new %s 1) (php/new %s 2)]))',
                self::FIXTURE,
                self::FIXTURE,
                self::FIXTURE,
            ),
            new CompileOptions(),
        );

        self::assertSame(['fixture-1', 'fixture-2'], $result);
    }

    public function test_an_instance_method_value_forwards_extra_arguments(): void
    {
        $result = $this->compilerFacade->eval(
            sprintf('(let [f %s/.repeatLabel] (f (php/new %s 1) 2))', self::FIXTURE, self::FIXTURE),
            new CompileOptions(),
        );

        self::assertSame('fixture-1fixture-1', $result);
    }

    public function test_a_constant_beats_a_static_method_of_the_same_name(): void
    {
        $result = $this->compilerFacade->eval(
            sprintf('%s/new', self::FIXTURE),
            new CompileOptions(),
        );

        self::assertSame('i-am-a-constant', $result);
    }

    public function test_the_shadowed_static_method_stays_reachable_in_call_position(): void
    {
        $result = $this->compilerFacade->eval(
            sprintf('(php/-> (%s/new 7) id)', self::FIXTURE),
            new CompileOptions(),
        );

        self::assertSame(7, $result);
    }

    public function test_a_plain_class_constant_is_unchanged(): void
    {
        $result = $this->compilerFacade->eval(
            sprintf('%s/LABEL', self::FIXTURE),
            new CompileOptions(),
        );

        self::assertSame('fixture', $result);
    }
}
