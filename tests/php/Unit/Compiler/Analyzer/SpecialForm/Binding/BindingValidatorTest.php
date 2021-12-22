<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm\Binding;

use Generator;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidator;
use Phel\Lang\AbstractType;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class BindingValidatorTest extends TestCase
{
    private BindingValidator $validator;

    public function setUp(): void
    {
        $this->validator = new BindingValidator();
    }

    public function test_integer_type(): void
    {
        $this->expectExceptionMessage('Cannot destructure integer');

        $this->validator->assertSupportedBinding(1);
    }

    public function test_float_type(): void
    {
        $this->expectExceptionMessage('Cannot destructure double');

        $this->validator->assertSupportedBinding(1.99);
    }

    public function test_string_type(): void
    {
        $this->expectExceptionMessage('Cannot destructure string');

        $this->validator->assertSupportedBinding('');
    }

    public function test_keyword_type(): void
    {
        $this->expectExceptionMessage('Cannot destructure Phel\Lang\Keyword');

        $this->validator->assertSupportedBinding(Keyword::create('any'));
    }

    /**
     * @dataProvider providerValidTypes
     *
     * @param AbstractType $type
     */
    public function test_valid_types($type): void
    {
        $this->validator->assertSupportedBinding($type);
        self::assertTrue(true); // this assertion ensures that no exception was thrown
    }

    public function providerValidTypes(): Generator
    {
        yield 'Symbol type' => [
            'type' => Symbol::create(''),
        ];

        yield 'Vector type' => [
            'type' => TypeFactory::getInstance()->persistentVectorFromArray([]),
        ];

        yield 'Map type' => [
            'type' => TypeFactory::getInstance()->emptyPersistentMap(),
        ];
    }
}
