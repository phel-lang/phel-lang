<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm\Binding;

use Generator;
use Phel;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidator;
use Phel\Lang\AbstractType;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BindingValidatorTest extends TestCase
{
    private BindingValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new BindingValidator();
    }

    public function test_integer_type(): void
    {
        $this->expectExceptionMessage('Cannot destructure int');

        $this->validator->assertSupportedBinding(1);
    }

    public function test_float_type(): void
    {
        $this->expectExceptionMessage('Cannot destructure float');

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

    public function test_qualified_symbol_binding_is_rejected(): void
    {
        $this->expectExceptionMessage("Can't bind qualified name: phel.core/count");

        $this->validator->assertSupportedBinding(Symbol::createForNamespace('phel.core', 'count'));
    }

    /**
     * @param AbstractType $type
     */
    #[DataProvider('providerValidTypes')]
    public function test_valid_types(mixed $type): void
    {
        // `assertSupportedBinding()` returns void: passing means it did not throw.
        $this->expectNotToPerformAssertions();

        $this->validator->assertSupportedBinding($type);
    }

    public static function providerValidTypes(): Generator
    {
        yield 'Symbol type' => [
            Symbol::create(''),
        ];

        yield 'Vector type' => [
            Phel::vector([]),
        ];

        yield 'Map type' => [
            Phel::map(),
        ];
    }
}
