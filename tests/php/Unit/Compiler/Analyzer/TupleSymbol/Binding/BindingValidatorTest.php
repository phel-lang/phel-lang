<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TupleSymbol\Binding;

use Generator;
use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\BindingValidator;
use Phel\Lang\AbstractType;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
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
        $this->expectExceptionMessage('Can not destructure integer');

        $this->validator->assertSupportedBinding(1);
    }

    public function testFloatType(): void
    {
        $this->expectExceptionMessage('Can not destructure double');

        $this->validator->assertSupportedBinding(1.99);
    }

    public function testStringType(): void
    {
        $this->expectExceptionMessage('Can not destructure string');

        $this->validator->assertSupportedBinding('');
    }

    public function testKeywordType(): void
    {
        $this->expectExceptionMessage('Can not destructure Phel\Lang\Keyword');

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

        yield 'Tuple type' => [
            'type' => Tuple::create(''),
        ];

        yield 'Table type' => [
            'type' => Table::fromKVs(),
        ];

        yield 'PhelArray type' => [
            'type' => PhelArray::create(''),
        ];
    }
}
