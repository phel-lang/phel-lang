<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Override;
use PDO;
use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Support\DefinesClassConstantCollisionTrait;
use PhelTest\Support\Fixtures\PhpInterop\QualifiedMemberFixture;
use PhelTest\Support\Fixtures\PhpInterop\StaticPropertyTarget;

use function class_exists;
use function sprintf;

final class QualifiedMemberValueRuntimeTest extends AbstractCompilerRuntimeTestCase
{
    use DefinesClassConstantCollisionTrait;

    private const string FIXTURE = '\\' . QualifiedMemberFixture::class;

    private const string STATIC_PROPERTY = '\\' . StaticPropertyTarget::class;

    private const string HOST_COLLISION = 'PHEL_TEST_RUNTIME_CLASS_CONSTANT_COLLISION';

    private const int HOST_CONSTANT_VALUE = 43;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::defineClassConstantCollision(
            self::HOST_COLLISION,
            QualifiedMemberFixture::class,
            self::HOST_CONSTANT_VALUE,
        );
    }

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

    public function test_a_static_property_is_read_through_the_sigil(): void
    {
        StaticPropertyTarget::$slot = 'i-am-a-property';

        try {
            $property = $this->compilerFacade->eval(self::STATIC_PROPERTY . '/$slot', new CompileOptions());
            $constant = $this->compilerFacade->eval(self::STATIC_PROPERTY . '/slot', new CompileOptions());
        } finally {
            StaticPropertyTarget::reset();
        }

        self::assertSame('i-am-a-property', $property);
        self::assertSame(StaticPropertyTarget::slot, $constant, 'the bare name stays the constant');
    }

    public function test_a_static_property_is_assigned_through_set(): void
    {
        try {
            $result = $this->compilerFacade->eval(
                sprintf('(set! %s/slot "written")', self::STATIC_PROPERTY),
                new CompileOptions(),
            );

            self::assertSame('written', $result, 'set! returns the assigned value');
            self::assertSame('written', StaticPropertyTarget::$slot);
            self::assertSame('i-am-a-constant', StaticPropertyTarget::slot, 'the constant is untouched');
        } finally {
            StaticPropertyTarget::reset();
        }
    }

    public function test_a_sigil_on_an_instance_member_fails_to_compile(): void
    {
        $this->expectException(CompilerException::class);
        $this->expectExceptionMessage("'\$foo' names a static property, which only a class can hold");

        $this->compilerFacade->eval(
            '(let [o (php/new \\stdClass)] (php/-> o $foo))',
            new CompileOptions(),
        );
    }

    public function test_an_all_caps_class_works_in_both_static_constant_spellings(): void
    {
        $qualified = $this->compilerFacade->eval(
            self::HOST_COLLISION . '/LABEL',
            new CompileOptions(),
        );
        $dot = $this->compilerFacade->eval(
            '(.-LABEL ' . self::HOST_COLLISION . ')',
            new CompileOptions(),
        );

        self::assertSame('fixture', $qualified);
        self::assertSame('fixture', $dot);
    }

    /**
     * The constant wins in value position, whether or not a class of that name
     * is loadable, so the same source compiles to the same PHP everywhere
     * (#3064). The class is reachable by three spellings; `php/NAME` remains
     * the explicit constant.
     */
    public function test_the_constant_wins_in_value_position(): void
    {
        $bare = $this->compilerFacade->eval(
            '(identity ' . self::HOST_COLLISION . ')',
            new CompileOptions(),
        );
        $constant = $this->compilerFacade->eval(
            'php/' . self::HOST_COLLISION,
            new CompileOptions(),
        );
        $class = $this->compilerFacade->eval(
            '(identity \\' . self::HOST_COLLISION . ')',
            new CompileOptions(),
        );

        self::assertSame(self::HOST_CONSTANT_VALUE, $bare);
        self::assertSame(self::HOST_CONSTANT_VALUE, $constant);
        self::assertSame(self::HOST_COLLISION, $class);
    }

    public function test_pdo_class_constants_need_no_leading_backslash(): void
    {
        if (!class_exists(PDO::class)) {
            self::markTestSkipped('The PDO extension is not available.');
        }

        $qualified = $this->compilerFacade->eval('PDO/ATTR_ERRMODE', new CompileOptions());
        $dot = $this->compilerFacade->eval('(.-ATTR_ERRMODE PDO)', new CompileOptions());

        self::assertSame(PDO::ATTR_ERRMODE, $qualified);
        self::assertSame(PDO::ATTR_ERRMODE, $dot);
    }
}
