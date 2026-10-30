<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use PHPUnit\Framework\Attributes\DataProvider;

final class DefStructFieldNamesRuntimeTest extends AbstractCompilerRuntimeTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function field_names(): iterable
    {
        yield 'apostrophe' => ["x'"];
        yield 'double quote' => ['say"hi'];
        yield 'dollar' => ['price$'];
        yield 'unicode' => ['café'];
    }

    #[DataProvider('field_names')]
    public function test_defstruct_field_name_survives_lookup_and_json_serialization(string $field): void
    {
        $name = 'field-' . bin2hex($field);
        $this->compilerFacade->eval('(ns struct-field-names)');
        $this->compilerFacade->eval('(defstruct ^{:php/json true} ' . $name . ' [' . $field . '])');

        $result = $this->compilerFacade->eval(
            '(let [value (' . $name . ' 1)] [(get value :' . $field . ') (php/json_encode value)])',
        );

        self::assertInstanceOf(PersistentVectorInterface::class, $result);
        self::assertSame([1, json_encode([$field => 1])], $result->toArray());
    }
}
