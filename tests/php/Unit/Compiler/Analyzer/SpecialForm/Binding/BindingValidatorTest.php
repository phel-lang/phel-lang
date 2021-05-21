<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm\Binding;

use Generator;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidator;
use Phel\Lang\AbstractType;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class BindingValidatorTest extends TestCase
{
    private BindingValidator $validator;

    public function setUp(): void
    {
        $this->validator = new BindingValidator();
    }

    public function testIntegerType(): void
    {
        $this->expectExceptionMessage('Cannot destructure integer');

        $this->validator->assertSupportedBinding(1);
    }

    public function testFloatType(): void
    {
        $this->expectExceptionMessage('Cannot destructure double');

        $this->validator->assertSupportedBinding(1.99);
    }

    public function testStringType(): void
    {
        $this->expectExceptionMessage('Cannot destructure string');

        $this->validator->assertSupportedBinding('');
    }

    public function testKeywordType(): void
    {
        $this->expectExceptionMessage('Cannot destructure Phel\Lang\Keyword');

        $this->validator->assertSupportedBinding(new Keyword('any'));
    }

    /**
     * @dataProvider providerValidTypes
     *
     * @param AbstractType $type
     */
    public function testValidTypes($type): void
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

        yield 'Table type' => [
            'type' => Table::fromKVs(),
        ];

        yield 'PhelArray type' => [
            'type' => PhelArray::create(''),
        ];
    }
}
